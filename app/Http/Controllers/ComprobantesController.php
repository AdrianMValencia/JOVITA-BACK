<?php

namespace App\Http\Controllers;

use App\Models\Comprobantes;
use App\Models\ComprobantesDetalles;
use App\Models\TipoComprobante;
use App\Models\SeriesTickets;
use App\Models\NumeracionTickets;
use App\Models\TipoDoi;
use App\Models\Productos;
use App\Models\EfactLog;
use App\Services\EfactCorrelativoCpeService;
use App\Services\EfactOseService;
use App\Services\EfactPdfPostProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ComprobantesController extends Controller
{
    /**
     * List comprobantes applying optional filters and pagination.
     *
     * Supported query params:
     * - tipo          : document type name or id
     * - idPuntoVenta  : filter by store/point of sale
     * - fechaDesde    : start date (Y-m-d)
     * - fechaHasta    : end date (Y-m-d)
     * - cliente       : cliente/cliente name to search
     * - page          : page number (defaults to 1)
     * - pageSize      : items per page (defaults to 10)
     */
    public function index(Request $request)
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
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
        $query = Comprobantes::query();

        // eager load type if relationship exists
        $query->with('tipo');

        if (!empty($filters['tipo'])) {
            // allow either type id or document name
            if (is_numeric($filters['tipo'])) {
                $query->where('idTipoComprobante', $filters['tipo']);
            } else {
                // attempt to filter by related type name if available
                $query->whereHas('tipo', function ($q) use ($filters) {
                    $q->where('documento', 'like', '%'.$filters['tipo'].'%');
                })->orWhere('tipo', 'like', '%'.$filters['tipo'].'%');
            }
        }

        if (!empty($filters['fechaDesde']) && !empty($filters['fechaHasta'])) {
            $desde = date('Y-m-d', strtotime($filters['fechaDesde']));
            $hasta = date('Y-m-d', strtotime($filters['fechaHasta']));
            $query->whereBetween('fecha', [$desde, $hasta]);
        }

        if (!empty($filters['idPuntoVenta'])) {
            $query->where('idPuntoVenta', $filters['idPuntoVenta']);
        }

        if (!empty($filters['cliente'])) {
            $cliente = $filters['cliente'];
            $query->where('cliente', 'like', "%{$cliente}%");
        }

        $page = isset($filters['page']) ? (int) $filters['page'] : 1;
        $pageSize = isset($filters['pageSize']) ? (int) $filters['pageSize'] : 10;

        $paginator = $query->orderBy('fecha', 'desc')
                           ->paginate($pageSize, ['*'], 'page', $page);

        // transform each result to match ComprobanteItem interface
        $items = $paginator->getCollection()->map(function ($item) {
            $tipoRelacion = $item->getRelation('tipo');

            return [
                'id' => $item->id,
                'idPuntoVenta' => $item->idPuntoVenta ?? null,
                'tipo' => $tipoRelacion ? $tipoRelacion->documento : ($item->tipo ?? null),
                'fecha' => $item->fecha,
                'serie' => $item->serie ?? null,
                'numeracion' => $item->numeracion ?? null,
                'numero' => $item->numero,
                'cliente' => $item->cliente,
                'codigo' => $item->codigo ?? null,
                'total' => $item->total,
                'idTipoCambio' => $item->idTipoCambio ?? null,
                'tipoCambio' => $item->tipoCambio ?? null,
                'igv' => $item->igv ?? null,
                'subTotal' => $item->subTotal ?? null,
                'idMoneda' => $item->idMoneda ?? null,
                'emitirEfact' => $item->emitirEfact ?? null,
                'efact_ticket' => $item->efact_ticket ?? null,
                'efact_estado' => $item->efact_estado ?? null,
                'efact_comprobante_serie' => $item->efact_comprobante_serie ?? null,
                'efact_comprobante_numero' => $item->efact_comprobante_numero ?? null,
            ];
        })->toArray();

        $data = [
            'items' => $items,
            'total' => $paginator->total(),
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    /**
     * Return available document types for filters/selection.
     */
    public function tipos()
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $tipos = TipoComprobante::orderBy('documento', 'asc')->get();

        return response()->json(['tipos' => $tipos, 'status' => 200], 200);
    }

    /**
     * Get available series codes.
     */
    public function series()
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

        // allow filtering by punto de venta so that only the series
        // belonging to the current store are returned. frontend will send
        // `idPuntoVenta` as query parameter.
        $idPunto = request()->query('idPuntoVenta');
        $series = SeriesTickets::where('idPuntoVenta', $idPunto)->get();
        
        return response()->json(['series' => $series, 'status' => 200], 200);
    }

    /**
     * Next numeration value for a given series.
     */
    public function numeracion(Request $request)
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

        $serie = $request->query('serie');
        if (empty($serie)) {
            return response()->json(['message' => 'serie parameter missing'], 400);
        }

        // optionally filter by punto de venta if provided
        $idPunto = $request->query('idPuntoVenta');
        $query = SeriesTickets::where('serie', $serie);
        if (! empty($idPunto)) {
            $query->where('idPuntoVenta', $idPunto);
        }
        $record = $query->first();

        // if the caller supplied a point but no row was found, fall back to any
        // series record so we at least return numeration instead of 404. this
        // mirrors the previous behaviour before the idPunto filter was added.
        $usedFallback = false;
        if (! $record && ! empty($idPunto)) {
            $record = SeriesTickets::where('serie', $serie)->first();
            $usedFallback = true;
        }
        if (! $record) {
            return response()->json(['message' => 'serie not found'], 404);
        }

        $numer = NumeracionTickets::where('idSeriesTickets', $record->id)
                    ->orderBy('id', 'desc')
                    ->first();

        $serieNorm = strtoupper(trim((string) $serie));
        $idPvInt = ! empty($idPunto) ? (int) $idPunto : null;
        /** @var EfactCorrelativoCpeService $cpeCorr */
        $cpeCorr = app(EfactCorrelativoCpeService::class);
        $ultimoEmitidoBd = $cpeCorr->maxUltimoEmitidoPorSerie($serieNorm, $idPvInt);
        if ($ultimoEmitidoBd === 0 && $idPvInt !== null) {
            $ultimoEmitidoBd = $cpeCorr->maxUltimoEmitidoPorSerie($serieNorm, null);
        }
        $desdeNumerador = $numer ? (int) ($numer->numeroActual ?? 0) : 0;
        // Próximo correlativo SUNAT: al menos último en BD + 1; el numerador no puede quedar por debajo.
        $siguiente = max($ultimoEmitidoBd + 1, $desdeNumerador, 1);

        $response = [
            'serie'         => $serie,
            'idSerie'       => $record->id,
            'idNumeracion'  => $numer ? $numer->id : null,
            'siguiente'     => $siguiente,
            'ultimo_correlativo_emitido_bd' => $ultimoEmitidoBd,
            'numeroActual_numerador' => $desdeNumerador,
            'siguiente_es_correlativo_a_emitir' => true,
            'status'        => 200,
        ];
        if ($usedFallback) {
            $response['warning'] = 'serie exists but not for requested puntoVenta';
        }

        return response()->json($response, 200);
    }

    /**
     * Guarda el comprobante localmente Y lo emite a la OSE eFact.
     * Endpoint: POST /api/comprobantes
     */
    public function store(Request $request)
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

        $cabecera = $request->input('cabecera');
        $detalle  = $request->input('detalle');

        if (! is_array($cabecera) || empty($cabecera)) {
            return response()->json(['message' => 'cabecera requerida'], 422);
        }
        if (! is_array($detalle)) {
            return response()->json(['message' => 'detalle debe ser array'], 422);
        }

        $emitirEfact = filter_var($cabecera['emitirEfact'] ?? $request->input('emitirEfact', true), FILTER_VALIDATE_BOOLEAN);
        $result = ['success' => false, 'body' => []];
        $ticket = null;

        // ── 1. Emitir en OSE eFact (opcional) ─────────────────────────────
        if ($emitirEfact) {
            /** @var EfactOseService $efact */
            $efact = app(EfactOseService::class);
            $documentoEfact = [
                'file' => $request->file('file'),
                'path' => $request->input('json_path', $request->input('xml_path')),
                'content' => $request->input('json_content', $request->input('json', $request->input('xml_content', $request->input('xml')))),
                'base64' => $request->input('json_base64', $request->input('xml_base64')),
                'filename' => $request->input('json_filename', $request->input('xml_filename', ($cabecera['serie'] ?? 'documento') . '-' . ($cabecera['numero'] ?? '0') . '.json')),
            ];
            $result = $efact->enviarDocumento($documentoEfact);
            if (($result['error_code'] ?? null) && str_starts_with($result['error_code'], 'document_')) {
                return response()->json([
                    'message' => $result['error'] ?? 'No se recibió el documento JSON/XML a emitir',
                    'status' => $result['status'] ?? 422,
                ], $result['status'] ?? 422);
            }
            $ticket  = $result['ticket'] ?? null;
            $efactEstado = $result['success'] ? 'ENVIADO' : 'ERROR';
        } else {
            $efactEstado = 'NO_ENVIADO';
        }

        // ── 2. Guardar localmente ─────────────────────────────────────────
        DB::beginTransaction();
        try {
            $cpeFacturacion = [];
            if ($emitirEfact && ($result['success'] ?? false)) {
                $serCab = strtoupper(trim((string) ($cabecera['serie'] ?? '')));
                $numRaw = $cabecera['numeracion'] ?? $cabecera['numero'] ?? 0;
                $numInt = max(1, (int) preg_replace('/\D+/', '', (string) $numRaw));
                $numPad = str_pad((string) $numInt, 8, '0', STR_PAD_LEFT);
                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                    $cpeFacturacion['efact_comprobante_serie'] = $serCab;
                }
                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
                    $cpeFacturacion['efact_comprobante_numero'] = $numPad;
                }
            }

            $cabeceraGuardar = array_merge($cabecera, [
                'emitirEfact' => $emitirEfact,
                'efact_ticket' => $ticket,
                'efact_estado' => $efactEstado,
            ], $cpeFacturacion);

            $facturacion = Comprobantes::create($cabeceraGuardar);

            foreach ($detalle as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $item['idComprobante'] = $facturacion->id;
                // normalizar nombres de claves enviados por el frontend
                if (isset($item['subtotal']) && ! isset($item['subTotal'])) {
                    $item['subTotal'] = $item['subtotal'];
                }
                if (isset($item['precio']) && ! isset($item['precioUnitario'])) {
                    $item['precioUnitario'] = $item['precio'];
                }
                ComprobantesDetalles::create($item);

                // ── Descontar stock del producto ──────────────────────────
                if (! empty($item['idProducto'])) {
                    $cantidad = max(0, (float) ($item['cantidad'] ?? 0));
                    if ($cantidad > 0) {
                        Productos::where('id', $item['idProducto'])
                            ->where('idPuntoVenta', $cabecera['idPuntoVenta'] ?? null)
                            ->where('stockActual', '>', 0)
                            ->decrement('stockActual', $cantidad);
                    }
                }
            }

            // Avanzar numeración: intentar por idNumeracion, luego por idSerie
            $idNumeracion = ! empty($facturacion->idNumeracion)
                ? $facturacion->idNumeracion
                : ($cabecera['idNumeracion'] ?? null);
            $idSerie = ! empty($facturacion->idSerie)
                ? $facturacion->idSerie
                : ($cabecera['idSerie'] ?? null);

            if ($idNumeracion) {
                NumeracionTickets::where('id', $idNumeracion)->increment('numeroActual');
            } elseif ($idSerie) {
                $numer = NumeracionTickets::where('idSeriesTickets', $idSerie)
                            ->orderBy('id', 'desc')
                            ->first();
                if ($numer) {
                    $numer->increment('numeroActual');
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar comprobante',
                'error'   => $e->getMessage(),
                'status'  => 500,
            ], 500);
        }

        // ── 3. Guardar log de eFact o de emisión omitida ──────────────────
        try {
            EfactLog::create([
                'idComprobante'  => $facturacion->id,
                'ticket'         => $ticket,
                'tipo_operacion' => $emitirEfact ? 'enviar_documento' : 'emision_omitida',
                'response_json'  => $emitirEfact
                    ? json_encode($result['body'] ?? [])
                    : json_encode(['message' => 'Emisión omitida por emitirEfact=false']),
                'status_code'    => $result['status'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // No interrumpir el flujo de venta por error de log.
        }

        // ── 3. Respuesta al frontend ──────────────────────────────────────
        if ($emitirEfact && ! $result['success']) {
            return response()->json([
                'message'        => 'Comprobante guardado pero con error en OSE eFact',
                'comprobante'    => $facturacion,
                'efact_error'    => $result['error'] ?? null,
                'efact_response' => $result['body']  ?? null,
                'status'         => 207,  // 207 Multi-Status: local OK, OSE falló
            ], 207);
        }

        if (! $emitirEfact) {
            return response()->json([
                'message'        => 'Comprobante guardado sin emisión a OSE eFact',
                'comprobante'    => $facturacion,
                'efact_ticket'   => null,
                'efact_response' => null,
                'status'         => 200,
            ], 200);
        }

        return response()->json([
            'message'        => 'Comprobante emitido y guardado correctamente',
            'comprobante'    => $facturacion,
            'efact_ticket'   => $ticket,
            'efact_response' => $result['body'] ?? null,
            'status'         => 200,
        ], 200);
    }

    /**
     * Emite un comprobante existente (reintento) o uno nuevo directamente a la OSE.
     * Endpoint: POST /api/comprobantes/efact
     */
    public function integrarEfact(Request $request)
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

        $cabecera = $request->input('cabecera', []);
        $detalle  = $request->input('detalle', []);

        if (empty($cabecera) || ! is_array($cabecera)) {
            return response()->json(['message' => 'cabecera requerida'], 422);
        }

        /** @var EfactOseService $efact */
        $efact = app(EfactOseService::class);
        $documentoEfact = [
            'file' => $request->file('file'),
            'path' => $request->input('json_path', $request->input('xml_path')),
            'content' => $request->input('json_content', $request->input('json', $request->input('xml_content', $request->input('xml')))),
            'base64' => $request->input('json_base64', $request->input('xml_base64')),
            'filename' => $request->input('json_filename', $request->input('xml_filename', ($cabecera['serie'] ?? 'documento') . '-' . ($cabecera['numero'] ?? '0') . '.json')),
        ];
        $result = $efact->enviarDocumento($documentoEfact);

        if (($result['error_code'] ?? null) && str_starts_with($result['error_code'], 'document_')) {
            return response()->json([
                'message' => $result['error'] ?? 'No se recibió el documento JSON/XML a emitir',
                'status' => $result['status'] ?? 422,
            ], $result['status'] ?? 422);
        }

        // Si existe el comprobante local, actualizamos ticket y estado
        if (! empty($cabecera['id'])) {
            $update = [
                'efact_ticket' => $result['ticket'] ?? null,
                'efact_estado' => $result['success'] ? 'ENVIADO' : 'ERROR',
            ];
            if ($result['success'] ?? false) {
                $serCab = strtoupper(trim((string) ($cabecera['serie'] ?? '')));
                $numRaw = $cabecera['numeracion'] ?? $cabecera['numero'] ?? 0;
                $numInt = max(1, (int) preg_replace('/\D+/', '', (string) $numRaw));
                $numPad = str_pad((string) $numInt, 8, '0', STR_PAD_LEFT);
                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                    $update['efact_comprobante_serie'] = $serCab;
                }
                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
                    $update['efact_comprobante_numero'] = $numPad;
                }
            }
            Comprobantes::where('id', $cabecera['id'])->update($update);
        }

        if (! $result['success']) {
            return response()->json([
                'message'        => 'Error al emitir en OSE eFact',
                'efact_response' => $result['body']  ?? null,
                'error'          => $result['error'] ?? null,
                'status'         => $result['status'] ?? 500,
            ], $result['status'] ?? 500);
        }

        return response()->json([
            'message'        => 'Comprobante emitido en OSE eFact',
            'efact_ticket'   => $result['ticket'],
            'efact_response' => $result['body'] ?? null,
            'status'         => 200,
        ], 200);
    }

    /**
     * Consulta el CDR de un comprobante por su ticket.
     * Endpoint: GET /api/comprobantes/{id}/cdr
     */
    public function cdr(int $id)
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

        $comprobante = Comprobantes::findOrFail($id);

        if (empty($comprobante->efact_ticket)) {
            return response()->json(['message' => 'Este comprobante no tiene ticket eFact'], 404);
        }

        /** @var EfactOseService $efact */
        $efact  = app(EfactOseService::class);
        $result = $efact->obtenerCdr($comprobante->efact_ticket);

        if (! $result['success']) {
            return response()->json([
                'message' => 'Error al obtener CDR',
                'error'   => $result['error'] ?? null,
                'body'    => $result['body']  ?? null,
            ], $result['status'] ?? 500);
        }

        // Guardar log del CDR
        EfactLog::create([
            'idComprobante'  => $comprobante->id,
            'ticket'         => $comprobante->efact_ticket,
            'tipo_operacion' => 'cdr',
            'response_json'  => json_encode([
                'filename' => $result['filename'] ?? null,
                'mime' => $result['mime'] ?? null,
            ]),
            'status_code'    => $result['status'] ?? null,
        ]);

        return response($result['content'], 200, [
            'Content-Type' => $result['mime'] ?? 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . ($result['filename'] ?? ('cdr-' . $comprobante->efact_ticket . '.xml')) . '"',
        ]);
    }

    /**
     * Obtiene el XML firmado de un comprobante.
     * Endpoint: GET /api/comprobantes/{id}/xml
     */
    public function xml(int $id)
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

        $comprobante = Comprobantes::findOrFail($id);

        if (empty($comprobante->efact_ticket)) {
            return response()->json(['message' => 'Este comprobante no tiene ticket eFact'], 404);
        }

        /** @var EfactOseService $efact */
        $efact  = app(EfactOseService::class);
        $result = $efact->obtenerXml($comprobante->efact_ticket);

        if (! $result['success']) {
            return response()->json([
                'message' => 'Error al obtener XML',
                'error'   => $result['error'] ?? null,
                'body'    => $result['body'] ?? null,
            ], $result['status'] ?? 500);
        }

        return response($result['content'], 200, [
            'Content-Type' => $result['mime'] ?? 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . ($result['filename'] ?? ('xml-' . $comprobante->efact_ticket . '.xml')) . '"',
        ]);
    }

    /**
     * Obtiene el PDF de un comprobante.
     * Endpoint: GET /api/comprobantes/{id}/pdf
     */
    public function pdf(int $id)
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

        $comprobante = Comprobantes::findOrFail($id);

        if (empty($comprobante->efact_ticket)) {
            return response()->json(['message' => 'Este comprobante no tiene ticket eFact'], 404);
        }

        /** @var EfactOseService $efact */
        $efact  = app(EfactOseService::class);
        $result = $efact->obtenerPdf($comprobante->efact_ticket);

        if (! $result['success']) {
            return response()->json([
                'message' => 'Error al obtener PDF',
                'error'   => $result['error'] ?? null,
                'body'    => $result['body'] ?? null,
            ], $result['status'] ?? 500);
        }

        $contenido = $result['content'];
        if (is_string($contenido) && $contenido !== '') {
            /** @var EfactPdfPostProcessor $pdfPost */
            $pdfPost = app(EfactPdfPostProcessor::class);
            $contenido = $pdfPost->personalizarContenidoPdf($contenido);
        }

        return response($contenido, 200, [
            'Content-Type' => $result['mime'] ?? 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . ($result['filename'] ?? ('pdf-' . $comprobante->efact_ticket . '.pdf')) . '"',
        ]);
    }

    /**
     * List of document id types for sunat / nubefact.
     */
    public function tiposDocumento()
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

        $tipos = TipoDoi::select('codigo','tipo')->get();
        return response()->json(['tipos' => $tipos, 'status' => 200], 200);
    }
}

