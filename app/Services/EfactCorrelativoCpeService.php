<?php

namespace App\Services;

use App\Models\Comprobantes;
use App\Models\NumeracionTickets;
use App\Models\Recibos;
use App\Models\SeriesTickets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Último correlativo CPE SUNAT emitido (máx. numérico) en facturación y recibos con columnas eFact.
 */
class EfactCorrelativoCpeService
{
    public function maxUltimoEmitidoPorSerie(string $serieNorm, ?int $idPuntoVenta): int
    {
        $serieNorm = strtoupper(trim($serieNorm));
        $m = 0;

        if (Schema::hasTable('tbl_facturacion')) {
            if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                $qE = Comprobantes::query()
                    ->whereRaw('UPPER(TRIM(CAST(efact_comprobante_serie AS CHAR))) = ?', [$serieNorm]);
                if ($idPuntoVenta !== null && $idPuntoVenta > 0) {
                    $qE->where('idPuntoVenta', $idPuntoVenta);
                }
                foreach (['efact_comprobante_numero', 'numeracion', 'numero'] as $col) {
                    if (Schema::hasColumn('tbl_facturacion', $col)) {
                        $mx = (int) ((clone $qE)->max(DB::raw('CAST(' . $col . ' AS UNSIGNED)')) ?? 0);
                        $m = max($m, $mx);
                    }
                }
            }

            if (Schema::hasColumn('tbl_facturacion', 'serie')) {
                $qLegacy = Comprobantes::query()
                    ->whereRaw('UPPER(TRIM(CAST(serie AS CHAR))) = ?', [$serieNorm]);
                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                    $qLegacy->where(function ($w) {
                        $w->whereNull('efact_comprobante_serie')
                            ->orWhereRaw('TRIM(CAST(efact_comprobante_serie AS CHAR)) = ?', ['']);
                    });
                }
                if ($idPuntoVenta !== null && $idPuntoVenta > 0) {
                    $qLegacy->where('idPuntoVenta', $idPuntoVenta);
                }
                foreach (['numeracion', 'numero'] as $col) {
                    if (Schema::hasColumn('tbl_facturacion', $col)) {
                        $mx = (int) ((clone $qLegacy)->max(DB::raw('CAST(' . $col . ' AS UNSIGNED)')) ?? 0);
                        $m = max($m, $mx);
                    }
                }
            }

            if (Schema::hasColumn('tbl_facturacion', 'serie')) {
                $qSer = Comprobantes::query()
                    ->whereRaw('UPPER(TRIM(CAST(serie AS CHAR))) = ?', [$serieNorm]);
                if ($idPuntoVenta !== null && $idPuntoVenta > 0) {
                    $qSer->where('idPuntoVenta', $idPuntoVenta);
                }
                foreach (['numeracion', 'numero', 'efact_comprobante_numero'] as $col) {
                    if (Schema::hasColumn('tbl_facturacion', $col)) {
                        $mx = (int) ((clone $qSer)->max(DB::raw('CAST(' . $col . ' AS UNSIGNED)')) ?? 0);
                        $m = max($m, $mx);
                    }
                }
            }
        }

        if (Schema::hasTable('tbl_recibos')
            && Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie')
            && Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero')) {
            $rq = Recibos::query()
                ->whereRaw('UPPER(TRIM(CAST(efact_comprobante_serie AS CHAR))) = ?', [$serieNorm]);
            if ($idPuntoVenta !== null && $idPuntoVenta > 0) {
                $rq->where('idPuntoVenta', $idPuntoVenta);
            }
            $mx = (int) ($rq->max(DB::raw('CAST(efact_comprobante_numero AS UNSIGNED)')) ?? 0);
            $m = max($m, $mx);
        }

        return $m;
    }

    /**
     * Siguiente correlativo disponible para una serie CPE SUNAT dada, combinando el
     * máximo ya emitido en BD con el `numeroActual` configurado en `tbl_numeracion_tickets`
     * (misma lógica que `RecibosController::numeracion()` cuando recibe `serieComprobante`).
     */
    public function resolverSiguienteCorrelativo(string $serieCpe, ?int $idPuntoVenta): int
    {
        $serieNorm = strtoupper(trim($serieCpe));

        $ultimoEmitidoBd = $this->maxUltimoEmitidoPorSerie($serieNorm, $idPuntoVenta);
        if ($ultimoEmitidoBd === 0 && $idPuntoVenta !== null) {
            $ultimoEmitidoBd = $this->maxUltimoEmitidoPorSerie($serieNorm, null);
        }

        $record = SeriesTickets::query()
            ->when($idPuntoVenta !== null && $idPuntoVenta > 0, fn ($q) => $q->where('idPuntoVenta', $idPuntoVenta))
            ->whereRaw('UPPER(TRIM(CAST(serie AS CHAR))) = ?', [$serieNorm])
            ->orderBy('id', 'asc')
            ->first();
        if (! $record) {
            $record = SeriesTickets::query()
                ->whereRaw('UPPER(TRIM(CAST(serie AS CHAR))) = ?', [$serieNorm])
                ->orderBy('id', 'asc')
                ->first();
        }

        $numer = $record
            ? NumeracionTickets::query()->where('idSeriesTickets', $record->id)->orderBy('id', 'desc')->first()
            : null;
        $desdeNumerador = $numer ? (int) ($numer->numeroActual ?? 0) : 0;

        return max($ultimoEmitidoBd + 1, $desdeNumerador, 1);
    }

    /**
     * Serie CPE SUNAT (boleta/factura) real que corresponde al punto de venta del recibo,
     * distinta de la serie del ticket POS interno (p. ej. "TJPR"), para no usar esta última
     * como si fuera el correlativo SUNAT al emitir/agrupar recibos pendientes.
     *
     * Prioriza la serie por defecto "BE01"/"FE01"; si no existe, cualquier otra serie del
     * punto de venta con el mismo prefijo que no sea la propia serie de ticket del recibo.
     * Si no logra determinar una serie distinta, retorna null (el llamador decide el fallback).
     */
    public function resolverSerieCpeDefaultParaRecibo(Recibos $r): ?string
    {
        $idPv = (int) ($r->idPuntoVenta ?? 0);
        if ($idPv <= 0) {
            return null;
        }

        $serieTicketPropia = strtoupper(trim((string) ($r->series ?? '')));
        $esFactura = $serieTicketPropia !== '' && str_starts_with($serieTicketPropia, 'F');
        $prefijo = $esFactura ? 'FE' : 'BE';
        $preferida = $prefijo . '01';

        $todas = SeriesTickets::query()
            ->where('idPuntoVenta', $idPv)
            ->orderBy('id', 'asc')
            ->pluck('serie')
            ->map(fn ($s) => strtoupper(trim((string) $s)))
            ->filter(fn ($s) => $s !== '');

        if ($todas->contains($preferida)) {
            return $preferida;
        }

        $candidata = $todas->first(fn ($s) => $s !== $serieTicketPropia && str_starts_with($s, $prefijo[0]));
        if ($candidata !== null) {
            return $candidata;
        }

        // El punto de venta usa una sola serie para ticket y CPE a la vez: es válida tal cual.
        if ($serieTicketPropia !== '' && str_starts_with($serieTicketPropia, $prefijo[0])) {
            return $serieTicketPropia;
        }

        return null;
    }
}
