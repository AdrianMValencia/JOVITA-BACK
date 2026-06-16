<?php

namespace App\Services;

use App\Models\Recibos;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de UI: ticket interno POS (tbl_recibos) ≠ CPE SUNAT (efact / tbl_facturacion).
 *
 * ticket_pos solo usa series + numeracion del recibo; nunca efact_comprobante_*.
 */
class EfactReciboVisorEnrichment
{
    public function __construct(
        private EfactFacturacionGemelaResolver $gemela
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function camposParaListado(Recibos $r): array
    {
        $serie = trim((string) ($r->series ?? ''));
        $numStr = trim((string) ($r->numeracion ?? ''));
        $enumeracion = $serie !== '' ? $serie . '-' . $numStr : $numStr;

        $hasTicket = Schema::hasColumn('tbl_recibos', 'efact_ticket');
        $hasEstado = Schema::hasColumn('tbl_recibos', 'efact_estado');
        $hasCpeS = Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie');
        $hasCpeN = Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero');
        $hasOse = Schema::hasColumn('tbl_recibos', 'efact_estado_ose');
        $hasSunat = Schema::hasColumn('tbl_recibos', 'efact_estado_sunat');

        $ticket = null;
        if ($hasTicket) {
            $rawT = trim((string) ($r->efact_ticket ?? ''));
            $ticket = $rawT !== '' ? $rawT : null;
        }

        $estadoRaw = $hasEstado ? (string) ($r->efact_estado ?? '') : '';
        $cpeS = $hasCpeS ? trim((string) ($r->efact_comprobante_serie ?? '')) : '';
        $cpeN = $hasCpeN ? trim((string) ($r->efact_comprobante_numero ?? '')) : '';

        $ose = $hasOse ? $r->efact_estado_ose : null;
        $sunat = $hasSunat ? $r->efact_estado_sunat : null;

        $g = $this->gemela->resolvePayload($r->idPuntoVenta, $serie, $r->numeracion);
        if ($g !== null) {
            if ($ticket === null && ($g['ticket'] ?? '') !== '') {
                $ticket = (string) $g['ticket'];
            }
            if ($estadoRaw === '' && ($g['estado'] ?? '') !== '') {
                $estadoRaw = (string) $g['estado'];
            }
            if ($cpeS === '' && ($g['cpe_serie'] ?? '') !== '') {
                $cpeS = (string) $g['cpe_serie'];
            }
            if ($cpeN === '' && ($g['cpe_numero'] ?? '') !== '') {
                $cpeN = (string) $g['cpe_numero'];
            }
            if (($ose === null || $ose === '') && array_key_exists('ose', $g) && $g['ose'] !== null && $g['ose'] !== '') {
                $ose = $g['ose'];
            }
            if (($sunat === null || $sunat === '') && array_key_exists('sunat', $g) && $g['sunat'] !== null && $g['sunat'] !== '') {
                $sunat = $g['sunat'];
            }
        }

        $cpeS = strtoupper($cpeS);
        $comprobanteElectronico = null;
        if ($cpeS !== '' && $cpeN !== '') {
            $comprobanteElectronico = [
                'serie' => $cpeS,
                'numero' => $cpeN,
                'comprobante' => $cpeS . '-' . $cpeN,
            ];
        }

        $norm = EfactEstadoNormalizer::normalizar(
            $ticket,
            $estadoRaw,
            is_string($ose) ? $ose : null,
            is_string($sunat) ? $sunat : null,
        );

        $tCanon = isset($norm['efact_ticket']) ? trim((string) $norm['efact_ticket']) : '';
        $aliases = $tCanon !== ''
            ? ['ose_ticket' => $tCanon, 'ticket_ose' => $tCanon, 'efactTicket' => $tCanon]
            : ['ose_ticket' => null, 'ticket_ose' => null, 'efactTicket' => null];

        $ticketPos = [
            'serie' => $serie,
            'numeracion' => (string) ($r->numeracion ?? ''),
            'texto' => $enumeracion,
        ];

        return array_merge([
            'enumeracion_ticket' => $enumeracion,
            'ticket_pos' => $ticketPos,
            'comprobante_electronico' => $comprobanteElectronico,
            'cpe_sunat' => $comprobanteElectronico,
            'comprobante_emitido' => $comprobanteElectronico['comprobante'] ?? null,
        ], $norm, $aliases);
    }
}
