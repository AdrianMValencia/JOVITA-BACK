<?php

namespace App\Services;

use App\Models\Comprobantes;
use App\Models\Recibos;
use Greenter\Ws\Reader\DomCdrReader;
use Greenter\Ws\Reader\XmlReader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza Estado OSE / Estado SUNAT consultando el CDR real en la OSE (como el portal eFact).
 */
class EfactEstadoSincronizadorService
{
    public function __construct(
        private EfactOseService $efact
    ) {}

    /**
     * @return array{ok: bool, estado_ose?: string, estado_sunat?: string, codigo_sunat?: ?string, descripcion_sunat?: ?string, error?: string, http_cdr?: ?int}
     */
    public function sincronizarPorTicket(string $ticket, string $origen, int $id): array
    {
        $ticket = trim($ticket);
        if ($ticket === '') {
            return ['ok' => false, 'error' => 'ticket vacío'];
        }

        $cdr = $this->efact->obtenerCdr($ticket);

        if (! ($cdr['success'] ?? false)) {
            $http = (int) ($cdr['status'] ?? 0);
            $ose = $this->etiquetaOseSinCdr($http);
            $sunat = 'Sin respuesta';
            $this->persistir($origen, $id, $ose, $sunat);

            return [
                'ok' => true,
                'estado_ose' => $ose,
                'estado_sunat' => $sunat,
                'http_cdr' => $http > 0 ? $http : null,
                'nota' => 'CDR no disponible o aún en proceso en OSE',
            ];
        }

        $parsed = $this->parsearCdr((string) ($cdr['content'] ?? ''));
        $sunat = $this->etiquetaSunatDesdeCdr($parsed);
        $ose = 'Validado';

        $this->persistir(
            $origen,
            $id,
            $ose,
            $sunat,
            $parsed['code'] ?? null,
            $parsed['description'] ?? null
        );

        return [
            'ok' => true,
            'estado_ose' => $ose,
            'estado_sunat' => $sunat,
            'codigo_sunat' => $parsed['code'] ?? null,
            'descripcion_sunat' => $parsed['description'] ?? null,
        ];
    }

    private function etiquetaOseSinCdr(int $httpStatus): string
    {
        if ($httpStatus === 401 || $httpStatus === 403) {
            return 'Error de autenticación OSE';
        }
        if ($httpStatus >= 500) {
            return 'Error OSE';
        }

        return 'Pendiente';
    }

    /**
     * @param  array{code?: ?string, description?: ?string}  $parsed
     */
    private function etiquetaSunatDesdeCdr(array $parsed): string
    {
        $code = isset($parsed['code']) ? trim((string) $parsed['code']) : '';
        $desc = isset($parsed['description']) ? trim((string) $parsed['description']) : '';

        if ($code === '' && $desc === '') {
            return 'Sin respuesta';
        }

        if ($code === '0') {
            return 'Aceptado';
        }

        $n = (int) $code;
        if ($n >= 4000) {
            return 'Observado';
        }

        if ($desc !== '') {
            return mb_substr($desc, 0, 120);
        }

        return 'Sin respuesta';
    }

    /**
     * @return array{code?: ?string, description?: ?string}
     */
    private function parsearCdr(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '' || ! str_starts_with($xml, '<')) {
            return [];
        }

        try {
            $reader = new XmlReader();
            $domReader = new DomCdrReader($reader);
            $cdr = $domReader->getCdrResponse($xml);

            return [
                'code' => $cdr->getCode(),
                'description' => $cdr->getDescription(),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function persistir(string $origen, int $id, string $ose, string $sunat, ?string $codigoSunat = null, ?string $descSunat = null): void
    {
        $line = 'OSE: ' . $ose . ' | SUNAT: ' . $sunat;

        if ($origen === 'recibo') {
            $r = Recibos::find($id);
            if (! $r) {
                return;
            }
            $this->aplicarColumnasEfact($r, 'tbl_recibos', $ose, $sunat, $line);
            $r->save();

            $this->propagarAFacturacion($r->idPuntoVenta, $r->series, $r->numeracion, $ose, $sunat, $line);

            return;
        }

        if ($origen === 'comprobante') {
            $c = Comprobantes::find($id);
            if (! $c) {
                return;
            }
            $this->aplicarColumnasEfact($c, 'tbl_facturacion', $ose, $sunat, $line);
            $c->save();

            $this->propagarARecibo($c->idPuntoVenta, $c->serie, $c->numeracion ?? $c->numero, $ose, $sunat, $line);
        }
    }

    private function aplicarColumnasEfact(Model $model, string $tabla, string $ose, string $sunat, string $line): void
    {
        if (Schema::hasColumn($tabla, 'efact_estado_ose')) {
            $model->setAttribute('efact_estado_ose', $ose);
        }
        if (Schema::hasColumn($tabla, 'efact_estado_sunat')) {
            $model->setAttribute('efact_estado_sunat', $sunat);
        }
        if (Schema::hasColumn($tabla, 'efact_estado')) {
            $model->setAttribute('efact_estado', $line);
        }
    }

    private function propagarAFacturacion($idPv, $serie, $numeracion, string $ose, string $sunat, string $line): void
    {
        if ($serie === null || $numeracion === null) {
            return;
        }

        $data = $this->payloadUpdateEstados('tbl_facturacion', $ose, $sunat, $line);
        if ($data === []) {
            return;
        }

        $base = Comprobantes::query()
            ->where(function ($q) use ($serie) {
                $s = trim((string) $serie);
                $q->where('serie', $s)->orWhereRaw('TRIM(CAST(serie AS CHAR)) = ?', [$s]);
            })
            ->where(function ($q) use ($numeracion) {
                $n = trim((string) $numeracion);
                $q->where('numeracion', $numeracion)
                    ->orWhere('numeracion', $n)
                    ->orWhere('numero', $numeracion)
                    ->orWhere('numero', $n);
                if ($n !== '') {
                    $q->orWhereRaw('TRIM(CAST(numeracion AS CHAR)) = ?', [$n])
                        ->orWhereRaw('TRIM(CAST(numero AS CHAR)) = ?', [$n]);
                }
            });

        $filtroPv = ! ($idPv === null || $idPv === '');
        if ($filtroPv) {
            $n = (clone $base)->where('idPuntoVenta', $idPv)->update($data);
            if ($n > 0) {
                return;
            }
        }

        $base->update($data);
    }

    private function propagarARecibo($idPv, $serie, $numeracion, string $ose, string $sunat, string $line): void
    {
        if ($serie === null || $numeracion === null) {
            return;
        }
        if (! Schema::hasTable('tbl_recibos')) {
            return;
        }

        $data = $this->payloadUpdateEstados('tbl_recibos', $ose, $sunat, $line);
        if ($data === []) {
            return;
        }

        $base = Recibos::query()
            ->where(function ($q) use ($serie) {
                $s = trim((string) $serie);
                $q->where('series', $s)->orWhereRaw('TRIM(CAST(series AS CHAR)) = ?', [$s]);
            })
            ->where(function ($q) use ($numeracion) {
                $n = trim((string) $numeracion);
                $q->where('numeracion', $numeracion)->orWhere('numeracion', $n);
                if ($n !== '') {
                    $q->orWhereRaw('TRIM(CAST(numeracion AS CHAR)) = ?', [$n]);
                }
            });

        $filtroPv = ! ($idPv === null || $idPv === '');
        if ($filtroPv) {
            $n = (clone $base)->where('idPuntoVenta', $idPv)->update($data);
            if ($n > 0) {
                return;
            }
        }

        $base->update($data);
    }

    /**
     * @return array<string, string>
     */
    private function payloadUpdateEstados(string $tabla, string $ose, string $sunat, string $line): array
    {
        $data = [];
        if (Schema::hasColumn($tabla, 'efact_estado_ose')) {
            $data['efact_estado_ose'] = $ose;
        }
        if (Schema::hasColumn($tabla, 'efact_estado_sunat')) {
            $data['efact_estado_sunat'] = $sunat;
        }
        if (Schema::hasColumn($tabla, 'efact_estado')) {
            $data['efact_estado'] = $line;
        }

        return $data;
    }
}
