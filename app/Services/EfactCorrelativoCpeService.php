<?php

namespace App\Services;

use App\Models\Comprobantes;
use App\Models\Recibos;
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
}
