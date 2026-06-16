<?php

namespace App\Services;

/**
 * Normaliza ticket y estados eFact (OSE vs SUNAT) para listados y flags coherentes con SUNAT.
 */
class EfactEstadoNormalizer
{
    /**
     * @return array{
     *   efact_ticket: ?string,
     *   efact_estado: ?string,
     *   estado_ose: ?string,
     *   estado_sunat: ?string,
     *   pendiente_emision: bool,
     *   puede_descargar: bool,
     *   es_error_critico: bool,
     *   cpe_cerrado_sunat_ose: bool
     * }
     */
    public static function normalizar(
        ?string $ticket,
        string $estadoTexto,
        ?string $estadoOse = null,
        ?string $estadoSunat = null
    ): array {
        $ticket = trim((string) $ticket);
        $ticket = $ticket === '' ? null : $ticket;

        $raw = trim($estadoTexto);
        $ose = trim((string) ($estadoOse ?? ''));
        $sunat = trim((string) ($estadoSunat ?? ''));

        if ($ose === '' && $sunat === '' && $raw !== '') {
            [$ose, $sunat] = self::partirEstadoCombinado($raw);
        }

        $up = strtoupper($raw);
        $oseU = strtoupper($ose);
        $sunatU = strtoupper($sunat);

        if ($oseU === '' && $up !== '') {
            $oseU = self::inferirOse($up);
        }
        if ($sunatU === '' && $up !== '') {
            $sunatU = self::inferirSunat($up);
        }

        $cerrado = self::esCierreExitoso($oseU, $sunatU, $up);
        $error = self::esErrorCritico($oseU, $sunatU, $up);

        $pendiente = $ticket === null || $error || ! $cerrado;

        $canonical = self::estadoCanonico($oseU, $sunatU, $raw);

        return [
            'efact_ticket' => $ticket,
            'efact_estado' => $canonical,
            'estado_ose' => $oseU !== '' ? $oseU : null,
            'estado_sunat' => $sunatU !== '' ? $sunatU : null,
            'pendiente_emision' => $pendiente,
            'puede_descargar' => $ticket !== null,
            'es_error_critico' => $error,
            'cpe_cerrado_sunat_ose' => $cerrado,
        ];
    }

    private static function partirEstadoCombinado(string $raw): array
    {
        $partes = preg_split('/\s*[\|\/;]\s*|\s+-\s+/', $raw) ?: [];
        $ose = '';
        $sunat = '';
        foreach ($partes as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $u = strtoupper($p);
            if (str_starts_with($u, 'OSE') || str_contains($u, 'VALIDADO') || str_contains($u, 'ENVIADO')) {
                $ose = $ose !== '' ? $ose : preg_replace('/^OSE\s*:\s*/i', '', $p);
            }
            if (str_starts_with($u, 'SUNAT') || str_contains($u, 'ACEPTADO') || str_contains($u, 'OBSERVADO') || str_contains($u, 'RECHAZADO')) {
                $sunat = $sunat !== '' ? $sunat : preg_replace('/^SUNAT\s*:\s*/i', '', $p);
            }
        }

        return [$ose, $sunat];
    }

    private static function inferirOse(string $up): string
    {
        if (str_contains($up, 'VALIDADO')) {
            return 'VALIDADO';
        }
        if (str_contains($up, 'ENVIADO')) {
            return 'ENVIADO';
        }
        if (str_contains($up, 'ERROR') || str_contains($up, 'RECHAZADO')) {
            return str_contains($up, 'OSE') ? trim(explode('|', $up)[0]) : '';
        }

        return '';
    }

    private static function inferirSunat(string $up): string
    {
        foreach (['ACEPTADO', 'ACEPTADA', 'OBSERVADO', 'OBSERVADA', 'RECHAZADO', 'RECHAZADA', 'ANULADO', 'ANULADA'] as $k) {
            if (str_contains($up, $k)) {
                return str_replace(['ACEPTADA', 'OBSERVADA', 'RECHAZADA', 'ANULADA'], ['ACEPTADO', 'OBSERVADO', 'RECHAZADO', 'ANULADO'], $k);
            }
        }
        if (str_contains($up, 'PROCESADO') && ! str_contains($up, 'RECHAZ')) {
            return 'PROCESADO';
        }

        return '';
    }

    /**
     * Cierre exitoso del flujo (OSE validó y/o SUNAT aceptó u observó sin rechazo explícito en texto).
     */
    private static function esCierreExitoso(string $oseU, string $sunatU, string $up): bool
    {
        if (str_contains($up, 'SIN RESPUESTA') || str_contains($sunatU, 'SIN RESPUESTA')) {
            return false;
        }
        if (str_contains($sunatU, 'RECHAZADO') || str_contains($up, 'RECHAZADO') || str_contains($sunatU, 'RECHAZADA') || str_contains($up, 'RECHAZADA')) {
            return false;
        }
        if (str_contains($sunatU, 'ANULADO') || str_contains($up, 'ANULADO') || str_contains($sunatU, 'ANULADA') || str_contains($up, 'ANULADA')) {
            return false;
        }
        if (str_contains($sunatU, 'ACEPTADO') || str_contains($up, 'ACEPTADO') || str_contains($sunatU, 'ACEPTADA') || str_contains($up, 'ACEPTADA')) {
            return true;
        }
        if (str_contains($sunatU, 'OBSERVADO') || str_contains($up, 'OBSERVADO') || str_contains($sunatU, 'OBSERVADA') || str_contains($up, 'OBSERVADA')) {
            return true;
        }
        if (str_contains($sunatU, 'PROCESADO') || (str_contains($up, 'PROCESADO') && ! str_contains($up, 'RECHAZ'))) {
            return true;
        }

        return false;
    }

    private static function esErrorCritico(string $oseU, string $sunatU, string $up): bool
    {
        if ($up === '' && $oseU === '' && $sunatU === '') {
            return false;
        }
        if (str_contains($up, 'NO_ENVIADO') || str_contains($oseU, 'NO_ENVIADO')) {
            return true;
        }
        if (str_contains($up, 'ERROR') || str_contains($oseU, 'ERROR')) {
            return true;
        }
        if (str_contains($sunatU, 'RECHAZADO') || str_contains($up, 'RECHAZADO') || str_contains($sunatU, 'RECHAZADA') || str_contains($up, 'RECHAZADA')) {
            return true;
        }

        return false;
    }

    private static function estadoCanonico(string $oseU, string $sunatU, string $raw): ?string
    {
        $partes = [];
        if ($sunatU !== '') {
            $partes[] = 'SUNAT: ' . $sunatU;
        }
        if ($oseU !== '') {
            $partes[] = 'OSE: ' . $oseU;
        }
        if ($partes !== []) {
            return implode(' | ', $partes);
        }

        return $raw !== '' ? $raw : null;
    }
}
