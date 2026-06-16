<?php

namespace App\Services;

use App\Models\Comprobantes;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciona un ticket POS (serie + correlativo) con su fila en tbl_facturacion
 * para obtener ticket OSE, estados y CPE SUNAT persistidos.
 */
class EfactFacturacionGemelaResolver
{
    /**
     * @return ?array{ticket: string, estado: string, ose: ?string, sunat: ?string, cpe_serie?: string, cpe_numero?: string}
     */
    public function resolvePayload(int|string|null $idPv, mixed $serie, mixed $numeracion): ?array
    {
        $c = $this->queryFacturacionGemelaPorSerieNumero($idPv, $serie, $numeracion);
        if (! $c && ! ($idPv === null || $idPv === '')) {
            $c = $this->queryFacturacionGemelaPorSerieNumero(null, $serie, $numeracion);
        }

        if (! $c) {
            return null;
        }

        $tNorm = $this->normalizarTicketEfact((string) ($c->efact_ticket ?? ''));
        $ticketStr = $tNorm ?? '';

        $cS = '';
        $cN = '';
        if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')
            && Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
            $cS = trim((string) ($c->efact_comprobante_serie ?? ''));
            $cN = trim((string) ($c->efact_comprobante_numero ?? ''));
        }

        $hasCpe = $cS !== '' && $cN !== '';
        if ($ticketStr === '' && ! $hasCpe) {
            return null;
        }

        $out = [
            'ticket' => $ticketStr,
            'estado' => (string) ($c->efact_estado ?? ''),
            'ose' => null,
            'sunat' => null,
        ];
        if (Schema::hasColumn('tbl_facturacion', 'efact_estado_ose')) {
            $out['ose'] = $c->efact_estado_ose ?? null;
        }
        if (Schema::hasColumn('tbl_facturacion', 'efact_estado_sunat')) {
            $out['sunat'] = $c->efact_estado_sunat ?? null;
        }
        if ($hasCpe) {
            $out['cpe_serie'] = strtoupper($cS);
            $out['cpe_numero'] = $cN;
        }

        return $out;
    }

    /**
     * @param  int|string|null  $idPv
     */
    private function queryFacturacionGemelaPorSerieNumero($idPv, mixed $serie, mixed $numeracion): ?Comprobantes
    {
        $cols = ['id', 'efact_ticket', 'efact_estado'];
        if (Schema::hasColumn('tbl_facturacion', 'efact_estado_ose')) {
            $cols[] = 'efact_estado_ose';
        }
        if (Schema::hasColumn('tbl_facturacion', 'efact_estado_sunat')) {
            $cols[] = 'efact_estado_sunat';
        }
        if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
            $cols[] = 'efact_comprobante_serie';
        }
        if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
            $cols[] = 'efact_comprobante_numero';
        }

        $serieNorm = trim((string) $serie);
        $numStr = trim((string) $numeracion);

        $q = Comprobantes::query()
            ->select($cols)
            ->where(function ($q) use ($serieNorm) {
                $q->where('serie', $serieNorm)
                    ->orWhereRaw('TRIM(CAST(serie AS CHAR)) = ?', [$serieNorm]);
            })
            ->where(function ($q) use ($numeracion, $numStr) {
                $q->where('numeracion', $numeracion)
                    ->orWhere('numeracion', $numStr)
                    ->orWhere('numero', $numeracion)
                    ->orWhere('numero', $numStr);
                if ($numStr !== '') {
                    $q->orWhereRaw('TRIM(CAST(numeracion AS CHAR)) = ?', [$numStr])
                        ->orWhereRaw('TRIM(CAST(numero AS CHAR)) = ?', [$numStr]);
                }
            });

        if ($idPv !== null && $idPv !== '') {
            $q->where('idPuntoVenta', $idPv);
        }

        /** @var Comprobantes|null */
        return $q->orderByDesc('id')->first();
    }

    private function normalizarTicketEfact(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);
        $t = trim($t, " \t\n\r\0\x0B\"'");
        if ($t === '') {
            return null;
        }
        if (preg_match('/^([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/i', $t, $m) === 1) {
            return strtolower($m[1]);
        }

        return $t;
    }
}
