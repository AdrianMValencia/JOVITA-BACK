<?php

namespace App\Services;

use App\Models\Clientes;
use App\Models\DatosEmpresa;
use App\Models\PuntoVenta;
use App\Models\TipoDoi;
use Illuminate\Support\Facades\Schema;

/**
 * Completa parámetros de emisión eFact con datos de tienda, cliente, hora y vendedor.
 */
class EfactEmisionContextEnricher
{
    public function enriquecer(array $params, ?Clientes $cliente = null, ?object $usuario = null): array
    {
        $idPv = isset($params['idPuntoVenta']) ? (int) $params['idPuntoVenta'] : 0;

        $empresa = $this->resolverDatosEmpresa($idPv);
        if ($empresa !== null) {
            $direccionEmpresa = trim((string) ($empresa->direccion ?? ''));
            if ($direccionEmpresa !== '') {
                $params['direccionEmisor'] = $direccionEmpresa;
            }
            $pagina = trim((string) ($empresa->pagina ?? ''));
            if ($pagina !== '') {
                $params['paginaEmisor'] = $pagina;
            }
        }

        if ($idPv > 0) {
            $puntoVenta = PuntoVenta::with('ubigeos')->find($idPv);
            if ($puntoVenta !== null) {
                if (empty($params['direccionEmisor'])) {
                    $dirPv = trim((string) ($puntoVenta->direccion ?? ''));
                    if ($dirPv !== '') {
                        $params['direccionEmisor'] = $dirPv;
                    }
                }

                $ubigeo = $puntoVenta->ubigeos;
                if ($ubigeo !== null) {
                    $params['ubigeoEmisor'] = $params['ubigeoEmisor'] ?? ($ubigeo->codigo ?? null);
                    $params['distritoEmisor'] = $params['distritoEmisor'] ?? ($ubigeo->distrito ?? null);
                    $params['provinciaEmisor'] = $params['provinciaEmisor'] ?? ($ubigeo->provincia ?? null);
                    $params['departamentoEmisor'] = $params['departamentoEmisor'] ?? ($ubigeo->departamento ?? null);
                }
            }
        }

        if ($cliente !== null) {
            $cliente->loadMissing('tipodoi');
            $numeroDoi = trim((string) ($cliente->numeroDoi ?? ''));
            if ($numeroDoi !== '' && $numeroDoi !== '-') {
                $params['numeroDocumento'] = $numeroDoi;
                $params['documento'] = $numeroDoi;
            }
            if (! empty($cliente->tipodoi?->codigo)) {
                $params['tipoDocumentoCliente'] = (string) $cliente->tipodoi->codigo;
            } elseif (! empty($cliente->idTipoDoi)) {
                $tipo = TipoDoi::find($cliente->idTipoDoi);
                if ($tipo !== null && ! empty($tipo->codigo)) {
                    $params['tipoDocumentoCliente'] = (string) $tipo->codigo;
                }
            }
        }

        $vendedor = trim((string) ($params['vendedor'] ?? ($params['nombreVendedor'] ?? '')));
        if ($vendedor === '' && $usuario !== null) {
            $vendedor = trim((string) ($usuario->nombre ?? ''));
        }
        if ($vendedor !== '') {
            $params['vendedor'] = $vendedor;
            $params['nombreVendedor'] = $vendedor;
        }

        $params['issueTime'] = $this->resolverIssueTime($params);

        return $params;
    }

    public function resolverDatosEmpresa(?int $idPuntoVenta): ?DatosEmpresa
    {
        if ($idPuntoVenta !== null && $idPuntoVenta > 0 && Schema::hasColumn('tbl_datos_empresa', 'idPuntoVenta')) {
            $porTienda = DatosEmpresa::query()
                ->where('idPuntoVenta', $idPuntoVenta)
                ->orderByDesc('id')
                ->first();
            if ($porTienda !== null) {
                return $porTienda;
            }
        }

        return DatosEmpresa::query()->orderByDesc('id')->first();
    }

    private function resolverIssueTime(array $params): string
    {
        if (! empty($params['issueTime'])) {
            $normalizada = $this->normalizarHoraEmision((string) $params['issueTime']);
            if ($normalizada !== '00:00:00') {
                return $normalizada;
            }
        }

        if (! empty($params['horaEmision'])) {
            $normalizada = $this->normalizarHoraEmision((string) $params['horaEmision']);
            if ($normalizada !== '00:00:00') {
                return $normalizada;
            }
        }

        $fechaRaw = $params['fechaEmision'] ?? ($params['fecha'] ?? null);
        if (is_string($fechaRaw) && $fechaRaw !== '') {
            $tieneHora = preg_match('/\d{1,2}:\d{2}/', $fechaRaw) === 1;
            if ($tieneHora) {
                try {
                    $dt = new \DateTime($fechaRaw);
                    $normalizada = $this->normalizarHoraEmision($dt->format('H:i:s'));
                    if ($normalizada !== '00:00:00') {
                        return $normalizada;
                    }
                } catch (\Throwable $e) {
                    // Continuar con hora actual.
                }
            }
        }

        return $this->normalizarHoraEmision(date('H:i:s'));
    }

    public function normalizarHoraEmision(string $hora): string
    {
        $hora = trim($hora);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $hora, $m)) {
            $h = str_pad((string) min(23, max(0, (int) $m[1])), 2, '0', STR_PAD_LEFT);
            $i = str_pad((string) min(59, max(0, (int) $m[2])), 2, '0', STR_PAD_LEFT);
            $s = str_pad((string) min(59, max(0, (int) ($m[3] ?? 0))), 2, '0', STR_PAD_LEFT);

            return "{$h}:{$i}:{$s}";
        }

        return date('H:i:s');
    }
}
