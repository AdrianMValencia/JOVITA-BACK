<?php

namespace App\Http\Controllers;

use App\Models\Comprobantes;
use App\Models\EfactLog;
use App\Models\Recibos;
use App\Models\TipoComprobante;
use App\Services\EfactEmisionParamsBuilder;
use App\Services\EfactEstadoNormalizer;
use App\Services\EfactEstadoSincronizadorService;
use App\Services\EfactOseService;
use App\Services\EfactPdfPostProcessor;
use App\Services\EfactRepresentacionImpresaPdfService;
use App\Services\JsonUblService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Integración OSE eFact: listado unificado, descargas por ticket y emisión en lote.
 *
 * La emisión en lote unifica ítems en un único POST /v1/document; el CPE (SUNAT) puede compartirse entre varios tickets POS.
 */
class EfactController extends Controller
{
    /**
     * GET /api/efact/emisiones
     *
     * Query: idPuntoVenta, fechaDesde, fechaHasta, cliente,
     *        estado (pendiente|completado|emitido|enviado|proceso|con_ticket|todos),
     *        origen (recibo|comprobante|todos), page, pageSize
     *
     * Los recibos POS van como origen "recibo". Los comprobantes sin par fila en tbl_recibos
     * (misma tienda, serie y número) se muestran como "comprobante".
     */
    public function emisiones(Request $request)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $filters = $request->query();
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $pageSize = isset($filters['pageSize']) ? max(1, min(200, (int) $filters['pageSize'])) : 15;
        $estado = strtolower((string) ($filters['estado'] ?? 'todos'));
        $modoListado = strtolower((string) ($filters['modoListado'] ?? 'emitidos'));
        if (! in_array($modoListado, ['emitidos', 'pendientes'], true)) {
            $modoListado = 'emitidos';
        }
        $origen = strtolower((string) ($filters['origen'] ?? 'todos'));

        $idPv = $filters['idPuntoVenta'] ?? null;
        $desde = ! empty($filters['fechaDesde']) ? date('Y-m-d', strtotime($filters['fechaDesde'])) : null;
        $hasta = ! empty($filters['fechaHasta']) ? date('Y-m-d', strtotime($filters['fechaHasta'])) : null;
        $cliente = $filters['cliente'] ?? null;

        $merged = [];
        $chunkSize = max(30, ($pageSize * 4));
        $offset = max(0, ($page - 1) * $pageSize);
        $hasReciboTicket = Schema::hasColumn('tbl_recibos', 'efact_ticket');
        $hasReciboEstado = Schema::hasColumn('tbl_recibos', 'efact_estado');
        $hasReciboEmitir = Schema::hasColumn('tbl_recibos', 'emitirEfact');
        $hasReciboOse = Schema::hasColumn('tbl_recibos', 'efact_estado_ose');
        $hasReciboSunat = Schema::hasColumn('tbl_recibos', 'efact_estado_sunat');
        $hasReciboCpeSerie = Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie');
        $hasReciboCpeNumero = Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero');
        $hasFacCpeSerie = Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie');
        $hasFacCpeNumero = Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero');

        if ($origen === 'todos' || $origen === 'recibo') {
            $rq = Recibos::query()->with(['puntoventa']);
            if ($idPv) {
                $rq->where('idPuntoVenta', $idPv);
            }
            if ($desde && $hasta) {
                $rq->whereBetween('fechaEmision', [$desde . ' 00:00:00', $hasta . ' 23:59:59']);
            }
            if (! empty($cliente)) {
                $rq->where('razonSocial', 'like', '%' . $cliente . '%');
            }

            foreach ($rq->orderBy('fechaEmision', 'desc')->skip($offset)->take($chunkSize)->get() as $r) {
                $ticket = $hasReciboTicket ? $r->efact_ticket : null;
                $efactEstado = $hasReciboEstado ? (string) ($r->efact_estado ?? '') : '';
                $emitir = $hasReciboEmitir ? (bool) $r->emitirEfact : null;
                $oseCol = $hasReciboOse ? ($r->efact_estado_ose ?? null) : null;
                $sunatCol = $hasReciboSunat ? ($r->efact_estado_sunat ?? null) : null;

                $cpeSerie = $hasReciboCpeSerie ? ($r->efact_comprobante_serie ?? null) : null;
                $cpeNum = $hasReciboCpeNumero ? ($r->efact_comprobante_numero ?? null) : null;

                $merged[] = [
                    '_sort'              => strtotime((string) $r->fechaEmision) ?: 0,
                    'origen'             => 'recibo',
                    'id'                 => $r->id,
                    'idPuntoVenta'       => $r->idPuntoVenta,
                    'puntoVentaNombre'   => $r->puntoventa->nombre ?? null,
                    'serie'              => $r->series,
                    'numero'             => $r->numeracion,
                    'serie_ticket'       => $r->series,
                    'numero_ticket'      => $r->numeracion,
                    'fecha'              => $r->fechaEmision,
                    'cliente'            => $r->razonSocial,
                    'total'              => $r->total,
                    'emitirEfact'        => $emitir,
                    'efact_comprobante_serie' => $cpeSerie,
                    'efact_comprobante_numero' => $cpeNum,
                    '_efact_ticket_raw'  => $ticket,
                    '_efact_estado_raw'  => $efactEstado,
                    '_efact_ose'         => $oseCol,
                    '_efact_sunat'       => $sunatCol,
                ];
            }
        }

        $hasFacOse = Schema::hasColumn('tbl_facturacion', 'efact_estado_ose');
        $hasFacSunat = Schema::hasColumn('tbl_facturacion', 'efact_estado_sunat');

        if ($origen === 'todos' || $origen === 'comprobante') {
            $cq = Comprobantes::query()->with(['tipo']);
            if ($idPv) {
                $cq->where('idPuntoVenta', $idPv);
            }
            if ($desde && $hasta) {
                $cq->whereBetween('fecha', [$desde, $hasta]);
            }
            if (! empty($cliente)) {
                $cq->where('cliente', 'like', '%' . $cliente . '%');
            }

            foreach ($cq->orderBy('fecha', 'desc')->skip($offset)->take($chunkSize)->get() as $c) {
                $ticket = $c->efact_ticket ?? null;
                $efactEstado = (string) ($c->efact_estado ?? '');
                $emitir = isset($c->emitirEfact) ? (bool) $c->emitirEfact : null;
                $oseCol = $hasFacOse ? ($c->efact_estado_ose ?? null) : null;
                $sunatCol = $hasFacSunat ? ($c->efact_estado_sunat ?? null) : null;

                $fechaVal = $c->fecha;
                $fechaStr = $fechaVal ? (string) $fechaVal : '';

                $numDoc = $c->numeracion ?? $c->numero;
                $cpeSerieF = $hasFacCpeSerie ? ($c->efact_comprobante_serie ?? null) : null;
                $cpeNumF = $hasFacCpeNumero ? ($c->efact_comprobante_numero ?? null) : null;

                $merged[] = [
                    '_sort'              => strtotime($fechaStr) ?: 0,
                    'origen'             => 'comprobante',
                    'id'                 => $c->id,
                    'idPuntoVenta'       => $c->idPuntoVenta,
                    'puntoVentaNombre'   => $c->puntoVenta ?? null,
                    'serie'              => $c->serie,
                    'numero'             => $numDoc,
                    'serie_ticket'       => $c->serie,
                    'numero_ticket'      => $numDoc,
                    'fecha'              => $fechaStr,
                    'cliente'            => $c->cliente,
                    'tipo'               => $this->etiquetaTipoComprobante($c),
                    'total'              => $c->total,
                    'emitirEfact'        => $emitir,
                    'efact_comprobante_serie' => $cpeSerieF,
                    'efact_comprobante_numero' => $cpeNumF,
                    '_efact_ticket_raw'  => $ticket,
                    '_efact_estado_raw'  => $efactEstado,
                    '_efact_ose'         => $oseCol,
                    '_efact_sunat'       => $sunatCol,
                ];
            }
        }

        // Evita duplicar la misma venta POS cuando existe en recibos y facturación:
        // clave = punto de venta + serie + numero. Se prioriza origen recibo.
        usort($merged, function ($a, $b) {
            $prioA = ($a['origen'] ?? '') === 'recibo' ? 0 : 1;
            $prioB = ($b['origen'] ?? '') === 'recibo' ? 0 : 1;
            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            return ($b['_sort'] <=> $a['_sort']);
        });
        $seen = [];
        $deduped = [];
        foreach ($merged as $row) {
            $key = implode('|', [
                (string) ($row['idPuntoVenta'] ?? ''),
                (string) ($row['serie'] ?? ''),
                (string) ($row['numero'] ?? ''),
            ]);
            if ($key !== '||' && isset($seen[$key])) {
                continue;
            }
            if ($key !== '||') {
                $seen[$key] = true;
            }
            $deduped[] = $row;
        }
        $merged = $deduped;

        /** @var EfactEmisionParamsBuilder $emisionTipoBuilder */
        $emisionTipoBuilder = app(EfactEmisionParamsBuilder::class);

        foreach ($merged as &$row) {
            if (($row['origen'] ?? '') === 'recibo') {
                $ticketRaw = trim((string) ($row['_efact_ticket_raw'] ?? ''));
                if ($ticketRaw === '') {
                    $g = $this->buscarTicketGemeloFacturacion(
                        $row['idPuntoVenta'] ?? null,
                        $row['serie'] ?? null,
                        $row['numero'] ?? null
                    );
                    if ($g !== null) {
                        $row['_efact_ticket_raw'] = $g['ticket'];
                        if (trim((string) ($row['_efact_estado_raw'] ?? '')) === '' && $g['estado'] !== '') {
                            $row['_efact_estado_raw'] = $g['estado'];
                        }
                        if (($row['_efact_ose'] ?? null) === null && array_key_exists('ose', $g)) {
                            $row['_efact_ose'] = $g['ose'];
                        }
                        if (($row['_efact_sunat'] ?? null) === null && array_key_exists('sunat', $g)) {
                            $row['_efact_sunat'] = $g['sunat'];
                        }
                        $cpeS = $g['cpe_serie'] ?? null;
                        $cpeN = $g['cpe_numero'] ?? null;
                        if (is_string($cpeS) && trim($cpeS) !== '' && is_string($cpeN) && trim($cpeN) !== '') {
                            if (trim((string) ($row['efact_comprobante_serie'] ?? '')) === '') {
                                $row['efact_comprobante_serie'] = $cpeS;
                            }
                            if (trim((string) ($row['efact_comprobante_numero'] ?? '')) === '') {
                                $row['efact_comprobante_numero'] = $cpeN;
                            }
                        }
                    }
                }

                $serieTipo = trim((string) ($row['efact_comprobante_serie'] ?? ''));
                if ($serieTipo === '') {
                    $serieTipo = trim((string) ($row['serie'] ?? ''));
                }
                $row['tipo'] = $emisionTipoBuilder->inferTipoComprobanteDesdeSerie($serieTipo);
            }

            $norm = EfactEstadoNormalizer::normalizar(
                $row['_efact_ticket_raw'] ?? null,
                (string) ($row['_efact_estado_raw'] ?? ''),
                $row['_efact_ose'] ?? null,
                $row['_efact_sunat'] ?? null,
            );
            unset($row['_efact_ticket_raw'], $row['_efact_estado_raw'], $row['_efact_ose'], $row['_efact_sunat']);
            $row = array_merge($row, $norm);
            $this->adjuntarComprobanteElectronicoDesdeColumnas($row);
            $this->intentarCompletarCpeDesdeTicketLog($row);
            $this->normalizarFlagsPorModoListado($row);
        }
        unset($row);

        // Cuando la emisión masiva comparte un solo ticket para múltiples ítems,
        // mostrar una sola fila consolidada en el listado.
        $merged = $this->compactarEmisionesMasivasPorTicket($merged);

        foreach ($merged as &$row) {
            $this->normalizarFlagsPorModoListado($row);
        }
        unset($row);

        $merged = array_values(array_filter($merged, fn ($row) => $this->pasaFiltroModoListado($row, $modoListado)));
        $merged = array_values(array_filter($merged, fn ($row) => $this->pasaFiltroEstadoLista($row, $estado)));

        usort($merged, function ($a, $b) {
            $byDate = ($b['_sort'] <=> $a['_sort']);
            if ($byDate !== 0) {
                return $byDate;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        $total = count($merged);
        $slice = array_slice($merged, ($page - 1) * $pageSize, $pageSize);
        $items = array_map(function ($row) {
            unset($row['_sort']);
            $row['enumeracion_ticket'] = $this->formatEnumeracionTicket($row);
            $this->adjuntarTicketPosYCpeSunat($row);
            $this->adjuntarAliasesTicketOse($row);

            return $row;
        }, $slice);

        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'modoListado' => $modoListado,
            'nota' => 'ticket_pos = correlativo interno POS. cpe_sunat = CPE SUNAT. efact_ticket / ose_ticket / ticket_ose / efactTicket = UUID OSE (mismo valor). PDF/XML/CDR: ?ticket|efact_ticket|efactTicket=UUID o ?origen=recibo|comprobante&id=… (solo ticket persistido o log, sin inferir por POS). sincronizar-estados: POST /api/efact/sincronizar-estados. Filtros estado: pendiente | completado | proceso | todos. modoListado: emitidos | pendientes.',
            'status' => 200,
        ], 200);
    }

    /**
     * Añade representación legible del CPE SUNAT cuando existen columnas persistidas.
     *
     * @param  array<string,mixed>  $row
     */
    private function adjuntarComprobanteElectronicoDesdeColumnas(array &$row): void
    {
        $s = trim((string) ($row['efact_comprobante_serie'] ?? ''));
        $n = trim((string) ($row['efact_comprobante_numero'] ?? ''));
        if ($s === '' || $n === '') {
            return;
        }
        $su = strtoupper($s);
        $row['comprobante_electronico'] = [
            'serie' => $su,
            'numero' => $n,
            'comprobante' => $su . '-' . $n,
        ];
        if (empty($row['comprobante_emitido'])) {
            $row['comprobante_emitido'] = $su . '-' . $n;
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function compactarEmisionesMasivasPorTicket(array $rows): array
    {
        $groups = [];
        $solo = [];
        $cacheEmitidos = [];

        foreach ($rows as $row) {
            $ticket = trim((string) ($row['efact_ticket'] ?? ''));
            if ($ticket === '') {
                $solo[] = $row;
                continue;
            }
            $groups[$ticket][] = $row;
        }

        $out = $solo;
        foreach ($groups as $ticket => $items) {
            if (count($items) === 1) {
                $out[] = $items[0];
                continue;
            }

            usort($items, fn ($a, $b) => ((int) ($b['_sort'] ?? 0)) <=> ((int) ($a['_sort'] ?? 0)));
            $base = $items[0];
            unset($base['comprobante_electronico'], $base['comprobante_emitido']);

            $total = 0.0;
            $seriesTicket = [];
            $numerosTicket = [];
            $cpeSeries = [];
            $cpeNumeros = [];
            $clientes = [];
            $ids = [];
            foreach ($items as $it) {
                $total += (float) ($it['total'] ?? 0);
                $serie = trim((string) ($it['serie_ticket'] ?? $it['serie'] ?? ''));
                $num = trim((string) ($it['numero_ticket'] ?? $it['numero'] ?? ''));
                $cli = trim((string) ($it['cliente'] ?? ''));
                if ($serie !== '') {
                    $seriesTicket[$serie] = true;
                }
                if ($num !== '') {
                    $numerosTicket[$num] = true;
                }
                $cs = trim((string) ($it['efact_comprobante_serie'] ?? ''));
                $cn = trim((string) ($it['efact_comprobante_numero'] ?? ''));
                if ($cs !== '' && $cn !== '') {
                    $cpeSeries[strtoupper($cs)] = true;
                    $cpeNumeros[$cn] = true;
                }
                if ($cli !== '') {
                    $clientes[$cli] = true;
                }
                $ids[] = (int) ($it['id'] ?? 0);
            }

            $base['total'] = round($total, 2);
            $base['id'] = max($ids);
            $base['ids_agrupados'] = array_values(array_filter(array_unique($ids), fn ($v) => $v > 0));
            $base['cantidad_agrupados'] = count($items);

            $emitidoDesdeBd = null;
            if (count($cpeSeries) === 1 && count($cpeNumeros) === 1) {
                $s = (string) array_key_first($cpeSeries);
                $n = (string) array_key_first($cpeNumeros);
                $emitidoDesdeBd = [
                    'serie' => $s,
                    'numero' => $n,
                    'comprobante' => $s . '-' . $n,
                ];
            }

            $emitido = $emitidoDesdeBd
                ?? ($cacheEmitidos[$ticket] ?? $this->resolverComprobanteEmitidoMasivoPorTicket($ticket));
            $cacheEmitidos[$ticket] = $emitido;

            $seriesList = array_keys($seriesTicket);
            $numsList = array_keys($numerosTicket);
            $clientesList = array_keys($clientes);

            $base['serie'] = count($seriesList) === 1 ? $seriesList[0] : 'VARIOS';
            $base['numero'] = count($numsList) === 1 ? $numsList[0] : 'VARIOS (' . count($numsList) . ')';
            $base['serie_ticket'] = $base['serie'];
            $base['numero_ticket'] = $base['numero'];

            if ($emitido !== null) {
                $base['comprobante_emitido'] = $emitido['comprobante'];
                $base['comprobante_electronico'] = [
                    'serie' => $emitido['serie'],
                    'numero' => $emitido['numero'],
                    'comprobante' => $emitido['comprobante'],
                ];
                $base['efact_comprobante_serie'] = $emitido['serie'];
                $base['efact_comprobante_numero'] = $emitido['numero'];
            }

            $base['cliente'] = count($clientesList) === 1 ? $clientesList[0] : 'CLIENTES VARIOS';

            if (($base['origen'] ?? '') === 'recibo') {
                $st = trim((string) ($base['efact_comprobante_serie'] ?? ''));
                if ($st === '') {
                    $st = trim((string) ($base['serie_ticket'] ?? $base['serie'] ?? ''));
                }
                $base['tipo'] = app(EfactEmisionParamsBuilder::class)->inferTipoComprobanteDesdeSerie($st);
            }

            $out[] = $base;
        }

        return $out;
    }

    /**
     * @return array{serie:string,numero:string,comprobante:string}|null
     */
    private function resolverComprobanteEmitidoMasivoPorTicket(string $ticket): ?array
    {
        $ticket = trim($ticket);
        if ($ticket === '') {
            return null;
        }

        $log = EfactLog::query()
            ->where('ticket', $ticket)
            ->whereIn('tipo_operacion', ['emision_lote_recibo', 'emision_lote_comprobante'])
            ->orderByDesc('id')
            ->first(['response_json']);
        if (! $log) {
            return null;
        }

        $data = json_decode((string) ($log->response_json ?? ''), true);
        if (! is_array($data)) {
            return null;
        }

        $serie = trim((string) ($data['lote']['serie'] ?? $data['serie_emitida'] ?? ''));
        $numeroRaw = trim((string) ($data['lote']['numero'] ?? $data['numero_emitido'] ?? ''));
        if ($serie === '' || $numeroRaw === '') {
            return null;
        }
        $numero = preg_replace('/\D+/', '', $numeroRaw) ?: $numeroRaw;
        if ($numero === '') {
            return null;
        }
        $numeroPad = str_pad((string) ((int) $numero), 8, '0', STR_PAD_LEFT);

        return [
            'serie' => strtoupper($serie),
            'numero' => $numeroPad,
            'comprobante' => strtoupper($serie) . '-' . $numeroPad,
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function pasaFiltroEstadoLista(array $row, string $estado): bool
    {
        $estado = strtolower(trim($estado));
        if ($estado === '' || $estado === 'todos') {
            return true;
        }

        $pendiente = $row['pendiente_emision'] ?? true;
        $ticket = trim((string) ($row['efact_ticket'] ?? ''));

        return match ($estado) {
            'pendiente', 'pendientes' => $pendiente,
            'emitido', 'enviado', 'completado' => ! $pendiente,
            'proceso', 'en_proceso', 'con_ticket' => $ticket !== '' && $pendiente,
            default => true,
        };
    }

    /**
     * Filtro explícito de listado:
     * - emitidos: ticket POS válido + CPE SUNAT válido
     * - pendientes: ticket POS válido + sin CPE SUNAT
     *
     * @param  array<string,mixed>  $row
     */
    private function pasaFiltroModoListado(array $row, string $modoListado): bool
    {
        $modoListado = strtolower(trim($modoListado));
        if ($modoListado === '') {
            $modoListado = 'emitidos';
        }

        $tieneTicketPos = $this->tieneTicketPosValido($row);
        $tieneCpeSunat = $this->tieneCpeSunatValido($row);

        return match ($modoListado) {
            'pendientes' => $tieneTicketPos && ! $tieneCpeSunat,
            default => $tieneTicketPos && $tieneCpeSunat,
        };
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function normalizarFlagsPorModoListado(array &$row): void
    {
        $tieneTicketPos = $this->tieneTicketPosValido($row);
        $tieneCpeSunat = $this->tieneCpeSunatValido($row);

        $row['pendiente_emision'] = $tieneTicketPos && ! $tieneCpeSunat;
        $row['seleccionable'] = (bool) $row['pendiente_emision'];
    }

    /**
     * Si el registro tiene ticket OSE pero no CPE persistido en columnas, intenta reconstruirlo
     * desde tbl_efact_logs (campos lote/serie_emitida/numero_emitido).
     *
     * @param  array<string,mixed>  $row
     */
    private function intentarCompletarCpeDesdeTicketLog(array &$row): void
    {
        if ($this->tieneCpeSunatValido($row)) {
            return;
        }

        $ticket = trim((string) ($row['efact_ticket'] ?? ''));
        if ($ticket === '') {
            return;
        }

        $emitido = $this->resolverComprobanteEmitidoMasivoPorTicket($ticket);
        if ($emitido === null) {
            return;
        }

        $row['efact_comprobante_serie'] = $emitido['serie'];
        $row['efact_comprobante_numero'] = $emitido['numero'];
        $row['comprobante_emitido'] = $emitido['comprobante'];
        $row['comprobante_electronico'] = [
            'serie' => $emitido['serie'],
            'numero' => $emitido['numero'],
            'comprobante' => $emitido['comprobante'],
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function tieneTicketPosValido(array $row): bool
    {
        $ticketPos = $row['ticket_pos'] ?? null;
        if (is_array($ticketPos)) {
            $texto = trim((string) ($ticketPos['texto'] ?? ''));
            if ($texto !== '') {
                return true;
            }
        }

        $enumeracion = trim((string) ($row['enumeracion_ticket'] ?? ''));
        if ($enumeracion !== '') {
            return true;
        }

        $serie = trim((string) ($row['serie_ticket'] ?? $row['serie'] ?? ''));
        $numero = trim((string) ($row['numero_ticket'] ?? $row['numero'] ?? ''));

        return $serie !== '' || $numero !== '';
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function tieneCpeSunatValido(array $row): bool
    {
        $emitido = trim((string) ($row['comprobante_emitido'] ?? ''));
        if ($emitido !== '') {
            return true;
        }

        $cpe = $row['comprobante_electronico'] ?? $row['cpe_sunat'] ?? null;
        if (is_array($cpe)) {
            $serie = trim((string) ($cpe['serie'] ?? ''));
            $numero = trim((string) ($cpe['numero'] ?? ''));
            if ($serie !== '' && $numero !== '') {
                return true;
            }
        }

        $serie = trim((string) ($row['efact_comprobante_serie'] ?? ''));
        $numero = trim((string) ($row['efact_comprobante_numero'] ?? ''));

        return $serie !== '' && $numero !== '';
    }

    private function etiquetaTipoComprobante(Comprobantes $c): ?string
    {
        $rel = $c->relationLoaded('tipo') ? $c->getRelation('tipo') : null;
        if ($rel instanceof TipoComprobante) {
            return $rel->documento;
        }

        $t = $c->getAttribute('tipo');

        return is_string($t) ? $t : null;
    }

    /**
     * Busca el comprobante en facturación que corresponde a un recibo (misma tienda, serie y número).
     * Usa criterios flexibles (numeracion vs numero, string vs int) porque en BD suele haber inconsistencias.
     *
     * @return ?array{ticket: string, estado: string, ose: ?string, sunat: ?string, cpe_serie?: string, cpe_numero?: string}
     */
    private function buscarTicketGemeloFacturacion($idPv, $serie, $numeracion): ?array
    {
        return app(\App\Services\EfactFacturacionGemelaResolver::class)->resolvePayload($idPv, $serie, $numeracion);
    }

    /**
     * Texto único de correlativo POS (serie-número) para listados.
     * Usa solo serie_ticket/numero_ticket (interno), nunca el CPE SUNAT.
     *
     * @param  array<string,mixed>  $row
     */
    private function formatEnumeracionTicket(array $row): string
    {
        $s = trim((string) ($row['serie_ticket'] ?? $row['serie'] ?? ''));
        $n = trim((string) ($row['numero_ticket'] ?? $row['numero'] ?? ''));

        return $s !== '' ? $s . '-' . $n : $n;
    }

    /**
     * Objeto explícito: ticket interno POS (columnas del recibo / serie_ticket del listado)
     * frente a CPE SUNAT (comprobante_electronico). El front debe usar estos nombres y no mezclar.
     *
     * @param  array<string,mixed>  $row
     */
    private function adjuntarTicketPosYCpeSunat(array &$row): void
    {
        $s = trim((string) ($row['serie_ticket'] ?? $row['serie'] ?? ''));
        $n = trim((string) ($row['numero_ticket'] ?? $row['numero'] ?? ''));
        $row['ticket_pos'] = [
            'serie' => $s,
            'numeracion' => $n,
            'texto' => $s !== '' ? $s . '-' . $n : $n,
        ];
        $ce = $row['comprobante_electronico'] ?? null;
        $row['cpe_sunat'] = is_array($ce) ? $ce : null;
    }

    /**
     * Alias del ticket OSE (misma clave que en POST recibos) para que el front no deduzca UUID.
     *
     * @param  array<string,mixed>  $row
     */
    private function adjuntarAliasesTicketOse(array &$row): void
    {
        $t = isset($row['efact_ticket']) ? trim((string) $row['efact_ticket']) : '';
        if ($t === '') {
            $row['ose_ticket'] = null;
            $row['ticket_ose'] = null;
            $row['efactTicket'] = null;

            return;
        }
        $row['ose_ticket'] = $t;
        $row['ticket_ose'] = $t;
        $row['efactTicket'] = $t;
    }

    /**
     * Ticket en recibo gemelo (cuando el comprobante no tiene ticket pero el POS sí).
     */
    private function buscarTicketGemeloRecibo($idPv, $serie, $numeracion): ?string
    {
        if ($serie === null || $numeracion === null) {
            return null;
        }
        if (! Schema::hasColumn('tbl_recibos', 'efact_ticket')) {
            return null;
        }

        $serieNorm = trim((string) $serie);
        $numStr = trim((string) $numeracion);

        $q = Recibos::query()
            ->where(function ($q) use ($serieNorm) {
                $q->where('series', $serieNorm)
                    ->orWhereRaw('TRIM(CAST(series AS CHAR)) = ?', [$serieNorm]);
            })
            ->where(function ($q) use ($numeracion, $numStr) {
                $q->where('numeracion', $numeracion)
                    ->orWhere('numeracion', $numStr);
                if ($numStr !== '') {
                    $q->orWhereRaw('TRIM(CAST(numeracion AS CHAR)) = ?', [$numStr]);
                }
            });

        if (! ($idPv === null || $idPv === '')) {
            $q->where('idPuntoVenta', $idPv);
        }

        $r = $q->orderByDesc('id')->first(['efact_ticket']);

        if (! $r) {
            return null;
        }

        return $this->normalizarTicketEfact((string) ($r->efact_ticket ?? ''));
    }

    /**
     * Fallback: en envíos desde POS el ticket quedó solo en log (idComprobante guarda id del recibo).
     */
    private function ticketDesdeEfactLogPorRecibo(int $idRecibo): ?string
    {
        if (! Schema::hasTable('tbl_efact_logs')) {
            return null;
        }

        $ticket = EfactLog::query()
            ->whereIn('tipo_operacion', ['enviar_recibo', 'emision_lote_recibo'])
            ->where('idComprobante', $idRecibo)
            ->whereNotNull('ticket')
            ->where('ticket', '!=', '')
            ->orderByDesc('id')
            ->value('ticket');

        $tn = $this->normalizarTicketEfact((string) $ticket);
        if ($tn !== null && $tn !== '') {
            return $tn;
        }

        $log = EfactLog::query()
            ->whereIn('tipo_operacion', ['enviar_recibo', 'emision_lote_recibo'])
            ->where('idComprobante', $idRecibo)
            ->whereNotNull('response_json')
            ->orderByDesc('id')
            ->first(['response_json']);

        if ($log) {
            $desdeJson = $this->extraerTicketDeJson($log->response_json);
            if ($desdeJson !== null && $desdeJson !== '') {
                return $this->normalizarTicketEfact($desdeJson);
            }
        }

        $ticketLibre = EfactLog::query()
            ->where('idComprobante', $idRecibo)
            ->whereNotNull('ticket')
            ->where('ticket', '!=', '')
            ->orderByDesc('id')
            ->value('ticket');

        $tn = $this->normalizarTicketEfact((string) $ticketLibre);
        if ($tn !== null && $tn !== '') {
            return $tn;
        }

        $logs = EfactLog::query()
            ->where('idComprobante', $idRecibo)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['ticket', 'response_json']);

        foreach ($logs as $row) {
            $tn = $this->normalizarTicketEfact((string) ($row->ticket ?? ''));
            if ($tn !== null && $tn !== '') {
                return $tn;
            }
            $desdeJson = $this->extraerTicketDeJson($row->response_json ?? null);
            if ($desdeJson !== null && $desdeJson !== '') {
                return $this->normalizarTicketEfact($desdeJson);
            }
            $uuid = $this->extraerTicketUuidDeTexto((string) ($row->response_json ?? ''));
            if ($uuid !== null) {
                return $uuid;
            }
        }

        return null;
    }

    private function ticketDesdeEfactLogPorComprobante(int $idComprobante): ?string
    {
        if (! Schema::hasTable('tbl_efact_logs')) {
            return null;
        }

        $ticket = EfactLog::query()
            ->whereIn('tipo_operacion', ['enviar_documento', 'emision_lote_comprobante'])
            ->where('idComprobante', $idComprobante)
            ->whereNotNull('ticket')
            ->where('ticket', '!=', '')
            ->orderByDesc('id')
            ->value('ticket');

        $tn = $this->normalizarTicketEfact((string) $ticket);
        if ($tn !== null && $tn !== '') {
            return $tn;
        }

        $log = EfactLog::query()
            ->whereIn('tipo_operacion', ['enviar_documento', 'emision_lote_comprobante'])
            ->where('idComprobante', $idComprobante)
            ->whereNotNull('response_json')
            ->orderByDesc('id')
            ->first(['response_json']);

        if ($log) {
            $desdeJson = $this->extraerTicketDeJson($log->response_json);
            if ($desdeJson !== null && $desdeJson !== '') {
                return $this->normalizarTicketEfact($desdeJson);
            }
        }

        $ticketLibre = EfactLog::query()
            ->where('idComprobante', $idComprobante)
            ->whereNotNull('ticket')
            ->where('ticket', '!=', '')
            ->orderByDesc('id')
            ->value('ticket');

        $tn = $this->normalizarTicketEfact((string) $ticketLibre);
        if ($tn !== null && $tn !== '') {
            return $tn;
        }

        $logs = EfactLog::query()
            ->where('idComprobante', $idComprobante)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['ticket', 'response_json']);

        foreach ($logs as $row) {
            $tn = $this->normalizarTicketEfact((string) ($row->ticket ?? ''));
            if ($tn !== null && $tn !== '') {
                return $tn;
            }
            $desdeJson = $this->extraerTicketDeJson($row->response_json ?? null);
            if ($desdeJson !== null && $desdeJson !== '') {
                return $this->normalizarTicketEfact($desdeJson);
            }
            $uuid = $this->extraerTicketUuidDeTexto((string) ($row->response_json ?? ''));
            if ($uuid !== null) {
                return $uuid;
            }
        }

        return null;
    }

    private function extraerTicketDeJson(?string $json): ?string
    {
        $data = json_decode((string) $json, true);
        if (! is_array($data)) {
            return null;
        }

        $candidatos = [
            $data['ticket'] ?? null,
            $data['data']['ticket'] ?? null,
            $data['body']['ticket'] ?? null,
            $data['result']['ticket'] ?? null,
        ];

        foreach ($candidatos as $c) {
            $s = trim((string) $c);
            if ($s !== '') {
                return $s;
            }
        }

        $recursivo = $this->buscarTicketRecursivoEnArray($data);

        return $recursivo !== null && $recursivo !== '' ? $recursivo : null;
    }

    /**
     * @return ?string UUID en minúsculas cuando aplica
     */
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

    private function extraerTicketUuidDeTexto(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/i', $text, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function buscarTicketRecursivoEnArray(array $data, int $depth = 0): ?string
    {
        if ($depth > 14) {
            return null;
        }
        foreach ($data as $k => $v) {
            if ($k === 'ticket' && (is_string($v) || is_numeric($v))) {
                $s = trim((string) $v);
                if ($s !== '') {
                    return $s;
                }
            }
            if (is_array($v)) {
                $found = $this->buscarTicketRecursivoEnArray($v, $depth + 1);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * GET /api/efact/cdr?ticket=... | ?origen=recibo|comprobante&id=...
     */
    public function cdrPorQuery(Request $request)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $ticket = $this->resolverTicketDesdeRequest($request);
        if ($ticket === null || $ticket === '') {
            return $this->jsonSinTicketEfact($request);
        }

        return $this->entregarArchivoEfact($ticket, 'cdr', true);
    }

    /**
     * GET /api/efact/xml?ticket=... | ?origen=recibo|comprobante&id=...
     */
    public function xmlPorQuery(Request $request)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $ticket = $this->resolverTicketDesdeRequest($request);
        if ($ticket === null || $ticket === '') {
            return $this->jsonSinTicketEfact($request);
        }

        return $this->entregarArchivoEfact($ticket, 'xml', false);
    }

    /**
     * GET /api/efact/pdf?ticket=... | ?origen=recibo|comprobante&id=...
     */
    public function pdfPorQuery(Request $request)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $ticket = $this->resolverTicketDesdeRequest($request);
        if ($ticket === null || $ticket === '') {
            return $this->jsonSinTicketEfact($request);
        }

        return $this->entregarArchivoEfact($ticket, 'pdf', false, $request);
    }

    /**
     * GET /api/efact/cdr/{ticket}
     */
    public function cdrPorTicket(string $ticket)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $ticket = $this->normalizarTicketEfact(trim(rawurldecode($ticket))) ?? '';
        if ($ticket === '') {
            return response()->json(['message' => 'ticket inválido'], 422);
        }

        return $this->entregarArchivoEfact($ticket, 'cdr', true);
    }

    /**
     * GET /api/efact/xml/{ticket}
     */
    public function xmlPorTicket(string $ticket)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $ticket = $this->normalizarTicketEfact(trim(rawurldecode($ticket))) ?? '';
        if ($ticket === '') {
            return response()->json(['message' => 'ticket inválido'], 422);
        }

        return $this->entregarArchivoEfact($ticket, 'xml', false);
    }

    /**
     * GET /api/efact/pdf/{ticket}
     */
    public function pdfPorTicket(string $ticket)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $ticket = $this->normalizarTicketEfact(trim(rawurldecode($ticket))) ?? '';
        if ($ticket === '') {
            return response()->json(['message' => 'ticket inválido'], 422);
        }

        return $this->entregarArchivoEfact($ticket, 'pdf', false);
    }

    /**
     * Texto legible devuelto por la OSE en JSON de error (p. ej. CDR/XML/PDF 412).
     *
     * @param  mixed  $body
     */
    private function mensajeDetalleOseDesdeBody($body): ?string
    {
        if (! is_array($body)) {
            return null;
        }
        $desc = $body['description'] ?? $body['Description'] ?? $body['message'] ?? $body['detail'] ?? null;
        $code = $body['code'] ?? $body['Code'] ?? null;
        $desc = is_string($desc) ? trim($desc) : (is_scalar($desc) ? trim((string) $desc) : '');
        if ($desc === '') {
            return null;
        }
        if ($code !== null && $code !== '') {
            return '[' . $code . '] ' . $desc;
        }

        return $desc;
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    private function entregarArchivoEfact(
        string $ticket,
        string $tipo,
        bool $registrarLogCdr,
        ?Request $request = null
    ) {
        $ticket = $this->normalizarTicketEfact($ticket) ?? trim($ticket);
        if ($ticket === '') {
            return response()->json(['message' => 'ticket inválido'], 422);
        }

        $defaultName = match ($tipo) {
            'cdr' => 'cdr-' . $ticket . '.xml',
            'xml' => 'xml-' . $ticket . '.xml',
            'pdf' => 'pdf-' . $ticket . '.pdf',
            default => 'archivo',
        };
        $mimeDefault = match ($tipo) {
            'cdr', 'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        /** @var EfactOseService $efact */
        $efact = app(EfactOseService::class);
        $result = match ($tipo) {
            'cdr' => $efact->obtenerCdr($ticket),
            'xml' => $efact->obtenerXml($ticket),
            'pdf' => $efact->obtenerPdf($ticket),
            default => ['success' => false, 'error' => 'tipo inválido', 'status' => 400],
        };

        if (! ($result['success'] ?? false)) {
            $mensajes = ['cdr' => 'Error al obtener CDR', 'xml' => 'Error al obtener XML', 'pdf' => 'Error al obtener PDF'];
            $baseMsg = $mensajes[$tipo] ?? 'Error al descargar';
            $bodyErr = $result['body'] ?? null;
            $detalleOse = $this->mensajeDetalleOseDesdeBody($bodyErr);
            $payload = [
                'message' => $detalleOse !== null && $detalleOse !== '' ? ($baseMsg . ' — ' . $detalleOse) : $baseMsg,
                'error' => $result['error'] ?? null,
                'body' => $bodyErr,
            ];
            if (is_array($bodyErr) && isset($bodyErr['code'])) {
                $payload['efact_code'] = $bodyErr['code'];
            }
            if (is_array($bodyErr) && isset($bodyErr['description'])) {
                $payload['efact_description'] = $bodyErr['description'];
            }

            return response()->json($payload, $result['status'] ?? 500);
        }

        if ($registrarLogCdr && $tipo === 'cdr') {
            try {
                EfactLog::create([
                    'idComprobante' => null,
                    'ticket' => $ticket,
                    'tipo_operacion' => 'cdr_por_ticket',
                    'response_json' => json_encode([
                        'filename' => $result['filename'] ?? null,
                        'mime' => $result['mime'] ?? null,
                    ]),
                    'status_code' => $result['status'] ?? null,
                ]);
            } catch (\Throwable $e) {
            }
        }

        $contenido = $result['content'];
        if ($tipo === 'pdf' && is_string($contenido) && $contenido !== '') {
            /** @var EfactPdfPostProcessor $pdfPost */
            $pdfPost = app(EfactPdfPostProcessor::class);
            $contenido = $pdfPost->personalizarContenidoPdf($contenido);
        }

        return response($contenido, 200, [
            'Content-Type' => $result['mime'] ?? $mimeDefault,
            'Content-Disposition' => 'attachment; filename="' . ($result['filename'] ?? $defaultName) . '"',
        ]);
    }

    private function resolverTicketDesdeRequest(Request $request): ?string
    {
        // Clave principal = UUID OSE tal cual se guardó al emitir (mismos nombres que el front).
        foreach (['ticket', 'efact_ticket', 'efactTicket'] as $q) {
            $t = $this->normalizarTicketEfact(trim((string) $request->query($q, '')));
            if ($t !== null && $t !== '') {
                return $t;
            }
        }

        $origen = strtolower((string) $request->query('origen', ''));
        $id = (int) $request->query('id', 0);
        if ($id <= 0 || ! in_array($origen, ['recibo', 'comprobante'], true)) {
            return null;
        }

        return $this->resolverTicketPorOrigenId($origen, $id);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    private function jsonSinTicketEfact(Request $request)
    {
        $origen = $request->query('origen');
        $idRaw = $request->query('id');

        return response()->json([
            'message' => 'No se encontró ticket eFact para la descarga en la base de datos para este origen e id.',
            'detalle' => 'Tras POST /v1/document, la OSE devuelve un ticket (UUID). Use ?ticket=UUID, ?efact_ticket=UUID o ?efactTicket=UUID (mismo valor). Si no tiene UUID, debe persistirse en tbl_recibos.efact_ticket / tbl_facturacion.efact_ticket o quedar en tbl_efact_logs al emitir; no se infiere por serie/número POS.',
            'ejemplo' => '/api/efact/pdf?ticket=2536e592-1552-4848-b65c-5e0f7526da5d',
            'origen_solicitado' => $origen,
            'id_solicitado' => is_numeric($idRaw) ? (int) $idRaw : null,
        ], 404);
    }

    /**
     * Resuelve ticket OSE para descargas: solo lo persistido en emisión (columna + log), sin inferir por POS/CPE.
     */
    private function resolverTicketPorOrigenId(string $origen, int $id): ?string
    {
        if ($origen === 'recibo') {
            $r = Recibos::find($id);
            if (! $r) {
                return null;
            }
            $hasTicket = Schema::hasColumn('tbl_recibos', 'efact_ticket');
            if ($hasTicket) {
                $ticket = $this->normalizarTicketEfact((string) ($r->efact_ticket ?? ''));
                if ($ticket !== null && $ticket !== '') {
                    return $ticket;
                }
            }

            return $this->ticketDesdeEfactLogPorRecibo($id);
        }

        $c = Comprobantes::find($id);
        if (! $c) {
            return null;
        }
        $ticket = $this->normalizarTicketEfact((string) ($c->efact_ticket ?? ''));
        if ($ticket !== null && $ticket !== '') {
            return $ticket;
        }

        return $this->ticketDesdeEfactLogPorComprobante($id);
    }

    /**
     * POST /api/efact/sincronizar-estados
     *
     * Body: { "items": [ { "origen": "recibo", "id": 489485 } ], "limite": 20 }
     * Consulta el CDR en la OSE por cada ticket y persiste estado OSE / SUNAT en BD (gemelo incluido).
     */
    public function sincronizarEstados(Request $request)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $items = $request->input('items');
        if (! is_array($items)) {
            return response()->json(['message' => 'items debe ser un array', 'status' => 422], 422);
        }

        $limite = max(1, min(40, (int) $request->input('limite', 20)));

        if (function_exists('set_time_limit')) {
            @set_time_limit(max(120, $limite * 45));
        }

        /** @var EfactEstadoSincronizadorService $sync */
        $sync = app(EfactEstadoSincronizadorService::class);

        $resultados = [];
        foreach (array_slice($items, 0, $limite) as $idx => $item) {
            try {
                if (! is_array($item)) {
                    $resultados[] = ['index' => $idx, 'ok' => false, 'error' => 'ítem inválido'];
                    continue;
                }

                $origen = strtolower((string) ($item['origen'] ?? ''));
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0 || ! in_array($origen, ['recibo', 'comprobante'], true)) {
                    $resultados[] = ['index' => $idx, 'ok' => false, 'error' => 'origen debe ser recibo o comprobante e id numérico'];
                    continue;
                }

                $ticket = $this->resolverTicketPorOrigenId($origen, $id);
                if ($ticket === null || $ticket === '') {
                    $resultados[] = [
                        'index' => $idx,
                        'origen' => $origen,
                        'id' => $id,
                        'ok' => false,
                        'error' => 'sin ticket resoluble en BD',
                    ];
                    continue;
                }

                $res = $sync->sincronizarPorTicket($ticket, $origen, $id);
                $resultados[] = array_merge([
                    'index' => $idx,
                    'origen' => $origen,
                    'id' => $id,
                    'efact_ticket' => $ticket,
                ], $res);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('efact sincronizar-estados ítem', [
                    'index' => $idx,
                    'item' => $item,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $resultados[] = [
                    'index' => $idx,
                    'origen' => is_array($item) ? strtolower((string) ($item['origen'] ?? '')) : null,
                    'id' => is_array($item) ? (int) ($item['id'] ?? 0) : null,
                    'ok' => false,
                    'error' => 'Error interno: ' . $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'resultados' => $resultados,
            'mensaje' => 'Sincronización desde CDR OSE. Vuelve a llamar GET /api/efact/emisiones para ver Estado OSE / SUNAT actualizado.',
            'status' => 200,
        ], 200);
    }

    /**
     * POST /api/efact/emision-lote
     *
     * Body: { "items": [ { "origen": "recibo", "id": 1 }, ... ], "reintentar": false }
     */
    public function emisionLote(Request $request)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $items = $request->input('items');
        if (! is_array($items) || $items === []) {
            return response()->json(['message' => 'items debe ser un array no vacío', 'status' => 422], 422);
        }

        $reintentar = filter_var($request->input('reintentar', false), FILTER_VALIDATE_BOOLEAN);
        $agruparEnUnComprobante = filter_var(
            $request->input('agrupar_en_un_comprobante', $request->input('un_solo_comprobante', false)),
            FILTER_VALIDATE_BOOLEAN
        );

        /** @var EfactEmisionParamsBuilder $builder */
        $builder = app(EfactEmisionParamsBuilder::class);
        /** @var JsonUblService $jsonService */
        $jsonService = app(JsonUblService::class);
        /** @var EfactOseService $efact */
        $efact = app(EfactOseService::class);

        $resultados = [];
        $emitibles = [];
        $comprobanteAgrupado = null;
        $ticketsAgrupados = [];

        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                $resultados[] = ['index' => $idx, 'ok' => false, 'error' => 'Ítem inválido'];
                continue;
            }

            $origen = strtolower((string) ($item['origen'] ?? ''));
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id <= 0 || ! in_array($origen, ['recibo', 'comprobante'], true)) {
                $resultados[] = ['index' => $idx, 'origen' => $origen ?: null, 'id' => $id, 'ok' => false, 'error' => 'origen debe ser recibo o comprobante e id numérico'];
                continue;
            }

            if ($origen === 'recibo') {
                $reg = Recibos::find($id);
                if (! $reg) {
                    $resultados[] = ['index' => $idx, 'origen' => 'recibo', 'id' => $id, 'ok' => false, 'error' => 'Recibo no encontrado'];
                    continue;
                }
                $ticketActual = Schema::hasColumn('tbl_recibos', 'efact_ticket') ? $reg->efact_ticket : null;
                $estadoActual = Schema::hasColumn('tbl_recibos', 'efact_estado') ? strtoupper((string) ($reg->efact_estado ?? '')) : '';
                if (! $reintentar && ! empty($ticketActual) && $estadoActual === 'ENVIADO') {
                    $resultados[] = ['index' => $idx, 'origen' => 'recibo', 'id' => $id, 'ok' => true, 'omitido' => true, 'motivo' => 'Ya emitido (use reintentar=true para volver a enviar)', 'efact_ticket' => $ticketActual];
                    continue;
                }
                $params = $builder->desdeRecibo($reg);
                $emitibles[] = ['index' => $idx, 'origen' => 'recibo', 'id' => $id, 'modelo' => $reg, 'params' => $params];
                continue;
            }

            $comp = Comprobantes::with('detalles')->find($id);
            if (! $comp) {
                $resultados[] = ['index' => $idx, 'origen' => 'comprobante', 'id' => $id, 'ok' => false, 'error' => 'Comprobante no encontrado'];
                continue;
            }
            $ticketActual = $comp->efact_ticket ?? null;
            $estadoActual = strtoupper((string) ($comp->efact_estado ?? ''));
            if (! $reintentar && ! empty($ticketActual) && $estadoActual === 'ENVIADO') {
                $resultados[] = ['index' => $idx, 'origen' => 'comprobante', 'id' => $id, 'ok' => true, 'omitido' => true, 'motivo' => 'Ya emitido (use reintentar=true para volver a enviar)', 'efact_ticket' => $ticketActual];
                continue;
            }
            $params = $builder->desdeComprobante($comp);
            $emitibles[] = ['index' => $idx, 'origen' => 'comprobante', 'id' => $id, 'modelo' => $comp, 'params' => $params];
        }

        if ($emitibles !== []) {
            $serieRef = trim((string) ($emitibles[0]['params']['serieComprobante'] ?? ''));
            $tipoRef = strtoupper(trim((string) ($emitibles[0]['params']['tipoComprobante'] ?? '')));
            $monedaRef = strtoupper(trim((string) ($emitibles[0]['params']['moneda'] ?? 'PEN')));
            $pvRef = (string) ($emitibles[0]['params']['idPuntoVenta'] ?? '');

            $conflictos = [];
            foreach ($emitibles as $row) {
                $s = trim((string) ($row['params']['serieComprobante'] ?? ''));
                $t = strtoupper(trim((string) ($row['params']['tipoComprobante'] ?? '')));
                $m = strtoupper(trim((string) ($row['params']['moneda'] ?? 'PEN')));
                $pv = (string) ($row['params']['idPuntoVenta'] ?? '');
                if ($s !== $serieRef || $t !== $tipoRef || $m !== $monedaRef || $pv !== $pvRef) {
                    $conflictos[] = $row;
                }
            }

            if ($conflictos !== []) {
                foreach ($conflictos as $c) {
                    $resultados[] = [
                        'index' => $c['index'],
                        'origen' => $c['origen'],
                        'id' => $c['id'],
                        'ok' => false,
                        'error' => 'No se puede unificar lote con serie/tipo/moneda/puntoVenta distintos',
                    ];
                }
                $emitibles = array_values(array_filter($emitibles, fn ($e) => ! in_array($e, $conflictos, true)));
            }

            if ($emitibles !== []) {
                $consolidado = $emitibles[0]['params'];
                $consolidado['detalles'] = [];
                $sumGravada = 0.0;
                $sumIgv = 0.0;
                $sumTotal = 0.0;
                foreach ($emitibles as $row) {
                    $p = $row['params'];
                    $sumGravada += (float) ($p['totalGravada'] ?? 0);
                    $sumIgv += (float) ($p['totalIgv'] ?? 0);
                    $sumTotal += (float) ($p['total'] ?? 0);
                    foreach ((array) ($p['detalles'] ?? []) as $d) {
                        $consolidado['detalles'][] = $d;
                    }
                }
                /** @var \App\Services\ComprobanteIgvService $igvService */
                $igvService = app(\App\Services\ComprobanteIgvService::class);
                $consolidado = $igvService->aplicarIgvAParams($consolidado);
                $consolidado['cliente'] = 'CLIENTES VARIOS';
                $consolidado['razonSocial'] = 'CLIENTES VARIOS';
                $consolidado['documento'] = '00000000';
                $consolidado['numeroDocumento'] = '00000000';

                $serie = (string) ($consolidado['serieComprobante'] ?? $serieRef ?: 'B001');
                $numero = (int) ($consolidado['numeroComprobante'] ?? 0);
                $numeroEmitido = max(1, $numero);
                $comprobanteEmitido = strtoupper(trim($serie)) . '-' . str_pad((string) $numeroEmitido, 8, '0', STR_PAD_LEFT);
                $jsonResult = $jsonService->generarJson($consolidado, $serie, $numeroEmitido);

                if (! ($jsonResult['success'] ?? false)) {
                    foreach ($emitibles as $row) {
                        $resultados[] = [
                            'index' => $row['index'],
                            'origen' => $row['origen'],
                            'id' => $row['id'],
                            'ok' => false,
                            'error' => $jsonResult['error'] ?? 'Error al generar JSON',
                        ];
                    }
                } else {
                    $documentoEfact = [
                        'content' => $jsonResult['json'],
                        'filename' => $jsonResult['filename'] ?? ($serie . '-' . $numeroEmitido . '.json'),
                    ];
                    $rutaRelativa = 'efact_documentos/' . date('Ymd') . '/' . ($documentoEfact['filename'] ?? 'doc.json');
                    Storage::disk('local')->put($rutaRelativa, (string) $documentoEfact['content']);

                    $envio = $efact->enviarDocumento($documentoEfact);
                    $ticket = $envio['ticket'] ?? null;
                    $ok = (bool) ($envio['success'] ?? false);
                    $efactEstado = $ok ? 'ENVIADO' : 'ERROR';
                    $cpeSerieU = strtoupper(trim((string) $serie));
                    $cpeNumPad = str_pad((string) $numeroEmitido, 8, '0', STR_PAD_LEFT);

                    if ($agruparEnUnComprobante && count($emitibles) > 1 && $ok) {
                        $comprobanteAgrupado = [
                            'serie' => $cpeSerieU,
                            'numero' => $cpeNumPad,
                            'comprobante' => $cpeSerieU . '-' . $cpeNumPad,
                            'efact_ticket' => $ticket,
                        ];
                        $ticketsAgrupados = array_values(array_map(
                            fn ($it) => ['origen' => $it['origen'], 'id' => $it['id']],
                            $emitibles
                        ));
                    }

                    foreach ($emitibles as $row) {
                        if ($row['origen'] === 'recibo') {
                            /** @var Recibos $reg */
                            $reg = $row['modelo'];
                            if (Schema::hasColumn('tbl_recibos', 'efact_ticket')) {
                                $reg->efact_ticket = $ticket;
                            }
                            if (Schema::hasColumn('tbl_recibos', 'efact_estado')) {
                                $reg->efact_estado = $efactEstado;
                            }
                            if (Schema::hasColumn('tbl_recibos', 'emitirEfact')) {
                                $reg->emitirEfact = true;
                            }
                            if ($ok) {
                                if (Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie')) {
                                    $reg->efact_comprobante_serie = $cpeSerieU;
                                }
                                if (Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero')) {
                                    $reg->efact_comprobante_numero = $cpeNumPad;
                                }
                            }
                            $reg->save();
                            $this->sincronizarComprobanteGemelo(
                                $reg->idPuntoVenta,
                                $reg->series,
                                $reg->numeracion,
                                $ticket,
                                $efactEstado,
                                $ok ? $cpeSerieU : null,
                                $ok ? $cpeNumPad : null
                            );
                            try {
                                EfactLog::create([
                                    'idComprobante' => $row['id'],
                                    'ticket' => $ticket,
                                    'tipo_operacion' => 'emision_lote_recibo',
                                    'response_json' => json_encode([
                                        'ticket' => $ticket,
                                        'ose_response' => $envio['body'] ?? [],
                                        'lote' => [
                                            'serie' => strtoupper(trim($serie)),
                                            'numero' => $numeroEmitido,
                                            'comprobante' => $comprobanteEmitido,
                                        ],
                                    ]),
                                    'status_code' => $envio['status'] ?? null,
                                ]);
                            } catch (\Throwable $e) {
                            }
                        } else {
                            /** @var Comprobantes $comp */
                            $comp = $row['modelo'];
                            $comp->efact_ticket = $ticket;
                            $comp->efact_estado = $efactEstado;
                            $comp->emitirEfact = true;
                            if ($ok) {
                                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                                    $comp->efact_comprobante_serie = $cpeSerieU;
                                }
                                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
                                    $comp->efact_comprobante_numero = $cpeNumPad;
                                }
                            }
                            $comp->save();
                            try {
                                EfactLog::create([
                                    'idComprobante' => $row['id'],
                                    'ticket' => $ticket,
                                    'tipo_operacion' => 'emision_lote_comprobante',
                                    'response_json' => json_encode([
                                        'ticket' => $ticket,
                                        'ose_response' => $envio['body'] ?? [],
                                        'lote' => [
                                            'serie' => strtoupper(trim($serie)),
                                            'numero' => $numeroEmitido,
                                            'comprobante' => $comprobanteEmitido,
                                        ],
                                    ]),
                                    'status_code' => $envio['status'] ?? null,
                                ]);
                            } catch (\Throwable $e) {
                            }
                        }

                        $resultados[] = [
                            'index' => $row['index'],
                            'origen' => $row['origen'],
                            'id' => $row['id'],
                            'ok' => $ok,
                            'efact_ticket' => $ticket,
                            'comprobante_emitido' => $comprobanteEmitido,
                            'error' => $ok ? null : ($envio['error'] ?? 'Error OSE'),
                            'efact_response' => $envio['body'] ?? null,
                        ];
                    }
                }
            }
        }

        usort($resultados, fn ($a, $b) => ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0)));
        $fallos = array_filter($resultados, fn ($r) => empty($r['ok']) && empty($r['omitido']));

        return response()->json([
            'resultados' => $resultados,
            'resumen' => [
                'total' => count($resultados),
                'errores' => count($fallos),
            ],
            'comprobante_agrupado' => $comprobanteAgrupado,
            'tickets_agrupados' => $ticketsAgrupados,
            'mensaje' => 'Se genera un único comprobante electrónico por todo el lote seleccionable y se comparte el mismo ticket en los ítems procesados.',
            'status' => 200,
        ], 200);
    }

    /**
     * Cuando el recibo tiene fila gemela en facturación (venta POS), copia ticket/estado
     * y, si se indica, serie/número del comprobante electrónico SUNAT.
     * Si no hay fila con idPuntoVenta (o viene vacío), reintenta solo por serie/numeración como en el listado gemelo.
     */
    private function sincronizarComprobanteGemelo($idPv, $serie, $numeracion, ?string $ticket, string $efactEstado, ?string $cpeSerie = null, ?string $cpeNumero = null): void
    {
        if ($serie === null || $numeracion === null) {
            return;
        }

        $payload = [
            'efact_ticket' => $ticket,
            'efact_estado' => $efactEstado,
            'emitirEfact' => true,
        ];
        if ($cpeSerie !== null && $cpeSerie !== '' && $cpeNumero !== null && $cpeNumero !== '') {
            if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                $payload['efact_comprobante_serie'] = $cpeSerie;
            }
            if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
                $payload['efact_comprobante_numero'] = $cpeNumero;
            }
        }

        $serieNorm = trim((string) $serie);
        $numStr = trim((string) $numeracion);

        $base = Comprobantes::query()
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

        $filtroPv = ! ($idPv === null || $idPv === '');
        if ($filtroPv) {
            $n = (clone $base)->where('idPuntoVenta', $idPv)->update($payload);
            if ($n > 0) {
                return;
            }
        }

        $base->update($payload);
    }

    private function generarPdfRepresentacionLocal(
        EfactRepresentacionImpresaPdfService $representacionPdf,
        string $ticket,
        ?Request $request = null
    ): ?string {
        if ($request !== null) {
            $origen = strtolower((string) $request->query('origen', ''));
            $id = (int) $request->query('id', 0);
            if ($origen === 'recibo' && $id > 0) {
                $pdf = $representacionPdf->generarPorReciboId($id);
                if (is_string($pdf) && $pdf !== '') {
                    return $pdf;
                }
            }
            if ($origen === 'comprobante' && $id > 0) {
                $pdf = $representacionPdf->generarPorComprobanteId($id);
                if (is_string($pdf) && $pdf !== '') {
                    return $pdf;
                }
            }
        }

        $recibo = $representacionPdf->buscarReciboPorTicket($ticket);
        if ($recibo !== null) {
            return $representacionPdf->generarDesdeRecibo($recibo);
        }

        $comprobante = $representacionPdf->buscarComprobantePorTicket($ticket);
        if ($comprobante !== null) {
            return $representacionPdf->generarDesdeComprobante($comprobante);
        }

        return null;
    }
}
