<?php

namespace App\Services;

use App\Models\Comprobantes;
use App\Models\ComprobantesDetalles;
use App\Models\Monedas;
use App\Models\Productos;
use App\Models\Recibos;

/**
 * Reconstruye el array de parámetros que espera JsonUblService::generarJson
 * a partir de un recibo o comprobante ya guardado (emisión / reintento / lote).
 */
class EfactEmisionParamsBuilder
{
    public function __construct(
        private readonly ComprobanteIgvService $igvService,
        private readonly EfactEmisionContextEnricher $contextEnricher,
        private readonly EfactCorrelativoCpeService $correlativoCpeService,
    ) {}

    public function desdeRecibo(Recibos $r): array
    {
        $r->loadMissing(['detalles.productos', 'clientes.tipodoi', 'monedas']);

        $detalles = [];
        foreach ($r->detalles as $d) {
            $det = [
                'nombre' => (string) ($d->nombre ?? $d->productos?->nombre ?? 'PRODUCTO'),
                'cantidad' => (float) ($d->cantidad ?? 0),
                'subtotal' => (float) ($d->subtotal ?? 0),
                'igv' => (float) ($d->igv ?? 0),
                'total' => (float) ($d->total ?? 0),
                'codigoBarra' => $d->codigoBarra ?? null,
                'idProducto' => $d->idProducto ?? null,
            ];
            if (! empty($d->codigo_afectacion_igv)) {
                $det['codigoAfectacionIgv'] = (string) $d->codigo_afectacion_igv;
            }
            $detalles[] = $det;
        }

        $cpe = $this->resolverSerieNumeroCpe($r);

        return $this->contextEnricher->enriquecer($this->igvService->aplicarIgvAParams([
            'idPuntoVenta'       => $r->idPuntoVenta,
            'puntoventa'         => $r->puntoventa,
            'tipoComprobante'    => $cpe['tipo'],
            'serieComprobante'   => $cpe['serie'],
            'numeroComprobante'  => $cpe['numero'],
            'fechaEmision'       => $r->fechaEmision,
            'documento'          => $r->documento,
            'numeroDocumento'    => $r->documento,
            'cliente'            => $r->razonSocial,
            'razonSocial'        => $r->razonSocial,
            'correo'             => $r->correo,
            'vendedor'           => $r->vendedor,
            'totalGravada'       => (float) ($r->totalGravada ?? 0),
            'totalIgv'           => (float) ($r->totalIgv ?? 0),
            'total'              => (float) ($r->total ?? 0),
            'moneda'             => $r->moneda,
            'tipoCambio'         => (float) ($r->tipoCambio ?? 1),
            'detalles'           => $detalles,
        ]), $r->clientes);
    }

    public function desdeComprobante(Comprobantes $c): array
    {
        $c->loadMissing(['detalles', 'tipo']);

        $idsProductos = $c->detalles
            ->pluck('idProducto')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $productosPorId = $idsProductos->isEmpty()
            ? collect()
            : Productos::query()->whereIn('id', $idsProductos->all())->get()->keyBy('id');

        $detalles = [];
        /** @var ComprobantesDetalles $d */
        foreach ($c->detalles as $d) {
            $prod = ! empty($d->idProducto) ? $productosPorId->get((int) $d->idProducto) : null;
            $nombre = $prod && isset($prod->nombre) ? (string) $prod->nombre : 'PRODUCTO';
            $detalles[] = [
                'nombre'       => $nombre,
                'cantidad'     => (float) ($d->cantidad ?? 0),
                'subtotal'     => (float) ($d->subTotal ?? $d->subtotal ?? 0),
                'igv'          => (float) ($d->igv ?? 0),
                'total'        => (float) ($d->total ?? 0),
                'idProducto'   => $d->idProducto ?? null,
            ];
        }

        $tipoNombre = $c->tipo?->documento ?? $c->tipo ?? 'BOLETA DE VENTA';
        $numero = (int) ($c->numeracion ?? $c->numero ?? 0);

        $monedaSigla = null;
        if (! empty($c->idMoneda)) {
            $mon = Monedas::find($c->idMoneda);
            if ($mon && ! empty($mon->abreviatura)) {
                $monedaSigla = (string) $mon->abreviatura;
            }
        }

        return $this->contextEnricher->enriquecer($this->igvService->aplicarIgvAParams([
            'idPuntoVenta'       => $c->idPuntoVenta,
            'puntoVenta'         => $c->puntoVenta,
            'tipoComprobante'    => (string) $tipoNombre,
            'serieComprobante'   => $c->serie,
            'numeroComprobante'  => $numero,
            'fechaEmision'       => $c->fecha,
            'documento'          => $c->codigo,
            'numeroDocumento'    => $c->codigo,
            'cliente'            => $c->cliente,
            'razonSocial'        => $c->cliente,
            'correo'             => $c->correo,
            'direccion'          => $c->direccion,
            'totalGravada'       => (float) ($c->subTotal ?? 0),
            'totalIgv'           => (float) ($c->igv ?? 0),
            'total'              => (float) ($c->total ?? 0),
            'moneda'             => $monedaSigla,
            'tipoCambio'         => (float) ($c->tipoCambio ?? 1),
            'detalles'           => $detalles,
        ]));
    }

    /**
     * CPE SUNAT real a usar al emitir/agrupar un recibo pendiente.
     *
     * - Si el recibo ya tiene `efact_comprobante_serie`/`efact_comprobante_numero` (reintento
     *   de una emisión previa), se respetan tal cual: nunca se pisa un correlativo ya asignado.
     * - Si no, se resuelve la serie de boleta/factura real del punto de venta (p. ej. "BE01")
     *   y se pide el siguiente correlativo disponible. Nunca se usa la serie del ticket POS
     *   interno (p. ej. "TJPR") como si fuera el CPE SUNAT.
     * - Si no fue posible resolver una serie CPE distinta, se conserva el comportamiento
     *   previo (serie/numeración del ticket) como último recurso.
     *
     * @return array{serie:mixed,numero:mixed,tipo:string}
     */
    private function resolverSerieNumeroCpe(Recibos $r): array
    {
        $serieAsignada = trim((string) ($r->efact_comprobante_serie ?? ''));
        $numeroAsignado = trim((string) ($r->efact_comprobante_numero ?? ''));
        if ($serieAsignada !== '' && $numeroAsignado !== '') {
            return [
                'serie' => $serieAsignada,
                'numero' => (int) $numeroAsignado,
                'tipo' => $this->inferTipoComprobanteDesdeSerie($serieAsignada),
            ];
        }

        $serieCpe = $this->correlativoCpeService->resolverSerieCpeDefaultParaRecibo($r);
        if ($serieCpe !== null) {
            return [
                'serie' => $serieCpe,
                'numero' => $this->correlativoCpeService->resolverSiguienteCorrelativo($serieCpe, (int) $r->idPuntoVenta),
                'tipo' => $this->inferTipoComprobanteDesdeSerie($serieCpe),
            ];
        }

        return [
            'serie' => $r->series,
            'numero' => $r->numeracion,
            'tipo' => $this->inferTipoComprobanteDesdeSerie((string) ($r->series ?? '')),
        ];
    }

    /**
     * Boleta / factura según prefijo de serie SUNAT (F… factura, resto boleta).
     */
    public function inferTipoComprobanteDesdeSerie(string $serie): string
    {
        $s = strtoupper(trim($serie));
        if ($s !== '' && str_starts_with($s, 'F')) {
            return 'FACTURA';
        }

        return 'BOLETA DE VENTA';
    }
}
