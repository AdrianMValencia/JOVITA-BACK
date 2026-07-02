<?php

namespace App\Services;

use App\Models\Comprobantes;
use App\Models\DatosEmpresa;
use App\Models\PuntoVenta;
use App\Models\Recibos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Representación impresa del CPE con los campos requeridos por el negocio
 * (hora real, vendedor y URL del OSE www.efact.pe).
 */
class EfactRepresentacionImpresaPdfService
{
    public function __construct(
        private readonly EfactEmisionContextEnricher $contextEnricher,
    ) {
    }

    public function generarDesdeRecibo(Recibos $recibo): ?string
    {
        try {
            // No usar loadMissing('puntoventa.*'): tbl_recibos.puntoventa es VARCHAR y choca con la relación homónima.
            $recibo->loadMissing(['detalles', 'clientes.tipodoi', 'usuarios']);
            $puntoVenta = $this->resolverPuntoVentaRecibo($recibo);

            $empresa = $this->contextEnricher->resolverDatosEmpresa(
                $recibo->idPuntoVenta !== null ? (int) $recibo->idPuntoVenta : null
            );
            if ($empresa === null) {
                return null;
            }

            [$serie, $numero] = $this->resolverSerieNumeroCpe($recibo);
            if ($serie === '' || $numero === '') {
                return null;
            }

            $fecha = $this->resolverFechaEmision($recibo);
            $hora = $this->resolverHoraEmision($recibo);
            $tipoDocCliente = $this->resolverTipoDocCliente($recibo);
            $numDocCliente = $this->formatearNumeroDocumentoCliente($recibo);
            $vendedor = $this->resolverNombreVendedor($recibo);
            $totalIgv = number_format((float) ($recibo->totalIgv ?? 0), 2, '.', '');
            $total = number_format((float) ($recibo->total ?? 0), 2, '.', '');
            $urlOse = $this->resolverUrlOse();

            $qrTexto = implode('|', [
                preg_replace('/\D/', '', (string) $empresa->ruc),
                '03',
                $serie,
                $numero,
                $totalIgv,
                $total,
                $fecha->format('Y-m-d'),
                $tipoDocCliente,
                preg_replace('/\D/', '', $numDocCliente) ?: '00000000',
            ]);

            $qrSrc = $this->resolverQrDataUri($qrTexto);

            $html = view('efact.representacion-impresa', [
                'empresa' => $empresa,
                'recibo' => $recibo,
                'puntoVenta' => $puntoVenta,
                'ubigeo' => $puntoVenta?->ubigeos,
                'serie' => $serie,
                'numero' => $numero,
                'fechaEmision' => $fecha->format('Y-m-d'),
                'horaEmision' => $hora,
                'vendedor' => $vendedor,
                'numDocCliente' => $numDocCliente,
                'urlOse' => $urlOse,
                'qrSrc' => $qrSrc,
                'detalles' => $recibo->detalles,
            ])->render();

            return $this->renderizarPdf($html);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function generarDesdeComprobante(Comprobantes $comprobante): ?string
    {
        $comprobante->loadMissing(['detalles']);

        $empresa = $this->contextEnricher->resolverDatosEmpresa(
            $comprobante->idPuntoVenta !== null ? (int) $comprobante->idPuntoVenta : null
        );
        if ($empresa === null) {
            return null;
        }

        $serie = trim((string) ($comprobante->efact_comprobante_serie ?? $comprobante->serie ?? ''));
        $numero = trim((string) ($comprobante->efact_comprobante_numero ?? $comprobante->numeracion ?? ''));
        if ($numero !== '' && ctype_digit($numero)) {
            $numero = str_pad($numero, 8, '0', STR_PAD_LEFT);
        }
        if ($serie === '' || $numero === '') {
            return null;
        }

        $fecha = $this->parseFecha($comprobante->fecha ?? null) ?? Carbon::now();
        $hora = $this->normalizarHora($comprobante->created_at?->format('H:i:s') ?? date('H:i:s'));
        $tipoDocCliente = strlen(preg_replace('/\D/', '', (string) ($comprobante->codigo ?? ''))) === 11 ? '6' : '1';
        $numDocCliente = $this->formatearNumeroDocumentoDesdeDigitos(
            preg_replace('/\D/', '', (string) ($comprobante->codigo ?? '')) ?: '00000000'
        );
        $totalIgv = number_format((float) ($comprobante->igv ?? 0), 2, '.', '');
        $total = number_format((float) ($comprobante->total ?? 0), 2, '.', '');
        $urlOse = $this->resolverUrlOse();

        $qrTexto = implode('|', [
            preg_replace('/\D/', '', (string) $empresa->ruc),
            str_starts_with(strtoupper($serie), 'F') ? '01' : '03',
            $serie,
            $numero,
            $totalIgv,
            $total,
            $fecha->format('Y-m-d'),
            $tipoDocCliente,
            $numDocCliente,
        ]);

        $html = view('efact.representacion-impresa', [
            'empresa' => $empresa,
            'recibo' => null,
            'puntoVenta' => null,
            'ubigeo' => null,
            'clienteNombre' => $comprobante->cliente,
            'serie' => $serie,
            'numero' => $numero,
            'fechaEmision' => $fecha->format('Y-m-d'),
            'horaEmision' => $hora,
            'vendedor' => '—',
            'numDocCliente' => $numDocCliente,
            'urlOse' => $urlOse,
            'qrSrc' => $this->resolverQrDataUri($qrTexto),
            'detalles' => $comprobante->detalles,
            'totalGravada' => (float) ($comprobante->subTotal ?? 0),
            'totalIgv' => (float) ($comprobante->igv ?? 0),
            'total' => (float) ($comprobante->total ?? 0),
            'moneda' => 'PEN',
        ])->render();

        return $this->renderizarPdf($html);
    }

    private function renderizarPdf(string $html): string
    {
        try {
            return Pdf::loadHTML($html)
                ->setPaper([0, 0, 226.77, 841.89], 'portrait')
                ->output();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'GD extension')) {
                $htmlSinQr = preg_replace('/<div class="qr">.*?<\/div>/s', '<div class="qr"></div>', $html) ?? $html;

                return Pdf::loadHTML($htmlSinQr)
                    ->setPaper([0, 0, 226.77, 841.89], 'portrait')
                    ->output();
            }

            throw $e;
        }
    }

    public function buscarReciboPorTicket(string $ticket): ?Recibos
    {
        $ticket = trim($ticket);
        if ($ticket === '') {
            return null;
        }

        return Recibos::query()
            ->where('efact_ticket', $ticket)
            ->orderByDesc('id')
            ->first();
    }

    public function buscarComprobantePorTicket(string $ticket): ?Comprobantes
    {
        $ticket = trim($ticket);
        if ($ticket === '') {
            return null;
        }

        return Comprobantes::query()
            ->where('efact_ticket', $ticket)
            ->orderByDesc('id')
            ->first();
    }

    private function resolverFechaEmision(Recibos $recibo): Carbon
    {
        return $this->parseFecha($recibo->fechaEmision ?? null)
            ?? ($recibo->created_at instanceof Carbon ? $recibo->created_at->copy() : Carbon::now());
    }

    private function resolverHoraEmision(Recibos $recibo): string
    {
        if ($recibo->created_at instanceof Carbon) {
            return $recibo->created_at->format('H:i:s');
        }

        return $this->normalizarHora(date('H:i:s'));
    }

    private function resolverTipoDocCliente(Recibos $recibo): string
    {
        if ($recibo->clientes?->tipodoi?->codigo) {
            return (string) $recibo->clientes->tipodoi->codigo;
        }

        $digits = preg_replace('/\D/', '', (string) ($recibo->documento ?? ''));

        return strlen($digits) === 11 ? '6' : '1';
    }

    public function generarPorReciboId(int $idRecibo): ?string
    {
        if ($idRecibo <= 0) {
            return null;
        }

        $recibo = Recibos::query()->find($idRecibo);

        return $recibo !== null ? $this->generarDesdeRecibo($recibo) : null;
    }

    public function generarPorComprobanteId(int $idComprobante): ?string
    {
        if ($idComprobante <= 0) {
            return null;
        }

        $comprobante = Comprobantes::query()->find($idComprobante);

        return $comprobante !== null ? $this->generarDesdeComprobante($comprobante) : null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolverSerieNumeroCpe(Recibos $recibo): array
    {
        $serie = strtoupper(trim((string) ($recibo->efact_comprobante_serie ?? '')));
        $numero = trim((string) ($recibo->efact_comprobante_numero ?? ''));
        if ($numero !== '' && ctype_digit($numero)) {
            $numero = str_pad($numero, 8, '0', STR_PAD_LEFT);
        }

        if (($serie === '' || $numero === '') && ! empty($recibo->efact_ticket)) {
            $comprobante = Comprobantes::query()
                ->where('efact_ticket', (string) $recibo->efact_ticket)
                ->orderByDesc('id')
                ->first();
            if ($comprobante !== null) {
                if ($serie === '') {
                    $serie = strtoupper(trim((string) ($comprobante->efact_comprobante_serie ?? $comprobante->serie ?? '')));
                }
                if ($numero === '') {
                    $numero = trim((string) ($comprobante->efact_comprobante_numero ?? $comprobante->numeracion ?? ''));
                    if ($numero !== '' && ctype_digit($numero)) {
                        $numero = str_pad($numero, 8, '0', STR_PAD_LEFT);
                    }
                }
            }
        }

        return [$serie, $numero];
    }

    private function resolverNombreVendedor(Recibos $recibo): string
    {
        $vendedor = trim((string) ($recibo->vendedor ?? ''));
        if ($vendedor !== '') {
            return $vendedor;
        }

        $vendedor = trim((string) ($recibo->usuarios?->nombre ?? ''));

        return $vendedor !== '' ? $vendedor : '—';
    }

    private function formatearNumeroDocumentoCliente(Recibos $recibo): string
    {
        $candidatos = [
            $recibo->clientes?->numeroDoi,
            $recibo->documento,
        ];

        foreach ($candidatos as $candidato) {
            $digitos = preg_replace('/\D/', '', (string) $candidato);
            if (strlen($digitos) >= 8) {
                return $this->formatearNumeroDocumentoDesdeDigitos($digitos);
            }
        }

        return $this->formatearNumeroDocumentoDesdeDigitos(
            preg_replace('/\D/', '', (string) ($recibo->documento ?? ''))
        );
    }

    private function formatearNumeroDocumentoDesdeDigitos(string $digitos): string
    {
        $digitos = preg_replace('/\D/', '', $digitos);
        if ($digitos === '') {
            return '00000000';
        }

        if (strlen($digitos) === 11) {
            return $digitos;
        }

        if (strlen($digitos) > 8) {
            $digitos = substr($digitos, 0, 8);
        }

        return str_pad($digitos, 8, '0', STR_PAD_LEFT);
    }

    private function resolverPuntoVentaRecibo(Recibos $recibo): ?PuntoVenta
    {
        $idPv = (int) ($recibo->idPuntoVenta ?? 0);
        if ($idPv <= 0) {
            return null;
        }

        return PuntoVenta::query()->with('ubigeos')->find($idPv);
    }

    private function resolverQrDataUri(string $qrTexto): ?string
    {
        $qrTexto = trim($qrTexto);
        if ($qrTexto === '' || ! extension_loaded('gd')) {
            return null;
        }

        try {
            $url = 'https://quickchart.io/qr?size=180&text=' . rawurlencode($qrTexto);
            $bin = @file_get_contents($url);
            if (! is_string($bin) || $bin === '') {
                return null;
            }

            return 'data:image/png;base64,' . base64_encode($bin);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolverUrlOse(): string
    {
        $url = trim((string) config('services.efact.web_url', 'www.efact.pe'));

        return preg_replace('#^https?://#i', '', $url) ?: 'www.efact.pe';
    }

    private function parseFecha(?string $fecha): ?Carbon
    {
        if ($fecha === null || trim($fecha) === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizarHora(string $hora): string
    {
        return $this->contextEnricher->normalizarHoraEmision($hora);
    }
}
