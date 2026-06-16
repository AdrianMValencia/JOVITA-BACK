<?php

namespace App\Support;

/**
 * Catálogo SUNAT 07 — afectación del IGV en líneas de venta (JSON+/UBL).
 *
 * @see https://cpe.sunat.gob.pe/sites/default/files/inline-files/anexo8-300-2012.pdf (Catálogos)
 */
final class SunatAfectacionIgv
{
    public const GRAVADO_OPERACION_ONEROSA = '10';

    public const EXONERADO = '20';

    public const INAFECTO = '30';

    /**
     * Indica si el producto del maestro está afecto a IGV (tbl_productos.igv).
     */
    public static function productoAfectoIgv(mixed $valor): bool
    {
        if ($valor === null) {
            return true;
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Resuelve el código de afectación por ítem (10 / 20 / 30).
     * Prioridad: codigo explícito → tipo textual → flag productoAfectoIgv / maestro producto.
     */
    public static function resolveCodigo(array $item, ?\App\Models\Productos $producto = null): string
    {
        if (isset($item['codigoAfectacionIgv'])) {
            $c = trim((string) $item['codigoAfectacionIgv']);

            return in_array($c, ['10', '20', '30'], true) ? $c : self::GRAVADO_OPERACION_ONEROSA;
        }

        if (! empty($item['tipoAfectacionIgv'])) {
            $t = strtolower(trim((string) $item['tipoAfectacionIgv']));
            if (in_array($t, ['exonerado', 'exonerada', 'exo'], true)) {
                return self::EXONERADO;
            }
            if (in_array($t, ['inafecto', 'inafecta', 'ina'], true)) {
                return self::INAFECTO;
            }
        }

        if (array_key_exists('productoAfectoIgv', $item) && ! self::productoAfectoIgv($item['productoAfectoIgv'])) {
            return self::INAFECTO;
        }

        if ($producto !== null && ! self::productoAfectoIgv($producto->igv)) {
            return self::INAFECTO;
        }

        return self::GRAVADO_OPERACION_ONEROSA;
    }

    public static function isGravadoOneroso(string $codigo): bool
    {
        return $codigo === self::GRAVADO_OPERACION_ONEROSA;
    }

    public static function requiereIgvCero(string $codigo): bool
    {
        return $codigo === self::EXONERADO || $codigo === self::INAFECTO;
    }
}
