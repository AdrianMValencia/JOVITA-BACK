<?php

namespace App\Services;

use App\Models\Productos;
use App\Support\SunatAfectacionIgv;
use Illuminate\Support\Collection;

/**
 * Normaliza líneas y totales de comprobantes/tickets para emisión mixta (gravado + inafecto/exonerado).
 */
class ComprobanteIgvService
{
    public function pctIgv(): float
    {
        $pct = (float) config('contabilidad.rce_compras_igv_pct', 18);

        return max(0.0, $pct) / 100.0;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public function normalizarLinea(array $line, ?Productos $producto = null): array
    {
        $codigo = SunatAfectacionIgv::resolveCodigo($line, $producto);
        $line['codigoAfectacionIgv'] = $codigo;

        $cantidad = max(0.0, (float) ($line['cantidad'] ?? 1));
        $total = round((float) ($line['total'] ?? 0), 2);
        $subtotal = round((float) ($line['subtotal'] ?? ($line['subTotal'] ?? 0)), 2);
        $igv = round((float) ($line['igv'] ?? 0), 2);
        $precio = (float) ($line['precio'] ?? ($line['precioUnitario'] ?? 0));

        if (SunatAfectacionIgv::requiereIgvCero($codigo)) {
            if ($total <= 0 && $subtotal > 0) {
                $total = $subtotal;
            } elseif ($subtotal <= 0 && $total > 0) {
                $subtotal = $total;
            } elseif ($total <= 0 && $subtotal <= 0 && $precio > 0 && $cantidad > 0) {
                $total = round($precio * $cantidad, 2);
                $subtotal = $total;
            }
            $line['subtotal'] = $subtotal;
            $line['subTotal'] = $subtotal;
            $line['igv'] = 0.0;
            $line['total'] = round($subtotal, 2);

            return $line;
        }

        $tasa = $this->pctIgv();
        if ($subtotal <= 0 && $total > 0 && $tasa > 0) {
            $subtotal = round($total / (1 + $tasa), 2);
            $igv = round($total - $subtotal, 2);
        } elseif ($igv <= 0 && $subtotal > 0) {
            $igv = round($subtotal * $tasa, 2);
            $total = round($subtotal + $igv, 2);
        } elseif ($total <= 0 && $subtotal > 0) {
            $igv = round($subtotal * $tasa, 2);
            $total = round($subtotal + $igv, 2);
        } elseif ($total <= 0 && $precio > 0 && $cantidad > 0) {
            $subtotal = round($precio * $cantidad, 2);
            $igv = round($subtotal * $tasa, 2);
            $total = round($subtotal + $igv, 2);
        }

        $line['subtotal'] = round($subtotal, 2);
        $line['subTotal'] = $line['subtotal'];
        $line['igv'] = round($igv, 2);
        $line['total'] = round($total > 0 ? $total : ($line['subtotal'] + $line['igv']), 2);

        return $line;
    }

    /**
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array<string, mixed>>
     */
    public function normalizarDetalles(array $detalles, ?Collection $productosPorId = null): array
    {
        $out = [];
        foreach ($detalles as $line) {
            if (! is_array($line)) {
                continue;
            }
            $idProd = isset($line['idProducto']) ? (int) $line['idProducto'] : 0;
            $producto = ($productosPorId !== null && $idProd > 0)
                ? $productosPorId->get($idProd)
                : null;
            if ($producto === null && $idProd > 0) {
                $producto = Productos::query()->find($idProd);
            }
            $out[] = $this->normalizarLinea($line, $producto);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $detallesNormalizados
     * @return array{totalGravada: float, totalIgv: float, total: float, totalInafecto: float, totalExonerado: float}
     */
    public function calcularTotalesDesdeDetalles(array $detallesNormalizados): array
    {
        $gravada = 0.0;
        $igv = 0.0;
        $inafecto = 0.0;
        $exonerado = 0.0;

        foreach ($detallesNormalizados as $line) {
            $codigo = SunatAfectacionIgv::resolveCodigo($line);
            $sub = round((float) ($line['subtotal'] ?? 0), 2);
            $lineIgv = round((float) ($line['igv'] ?? 0), 2);

            if (SunatAfectacionIgv::isGravadoOneroso($codigo)) {
                $gravada += $sub;
                $igv += $lineIgv;
            } elseif ($codigo === SunatAfectacionIgv::EXONERADO) {
                $exonerado += $sub;
            } else {
                $inafecto += $sub;
            }
        }

        $gravada = round($gravada, 2);
        $igv = round($igv, 2);
        $inafecto = round($inafecto, 2);
        $exonerado = round($exonerado, 2);
        $total = round($gravada + $igv + $inafecto + $exonerado, 2);

        return [
            'totalGravada' => $gravada,
            'totalIgv' => $igv,
            'total' => $total,
            'totalInafecto' => $inafecto,
            'totalExonerado' => $exonerado,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function aplicarIgvAParams(array $params): array
    {
        $detalles = is_array($params['detalles'] ?? null) ? $params['detalles'] : [];
        if ($detalles === []) {
            return $params;
        }

        $ids = collect($detalles)
            ->pluck('idProducto')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $productosPorId = $ids->isEmpty()
            ? collect()
            : Productos::query()->whereIn('id', $ids->all())->get()->keyBy('id');

        $detallesNorm = $this->normalizarDetalles($detalles, $productosPorId);
        $totales = $this->calcularTotalesDesdeDetalles($detallesNorm);

        $params['detalles'] = $detallesNorm;
        $params['totalGravada'] = $totales['totalGravada'];
        $params['totalIgv'] = $totales['totalIgv'];
        $params['total'] = $totales['total'];

        return $params;
    }
}
