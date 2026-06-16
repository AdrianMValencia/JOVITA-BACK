<?php

namespace App\Http\Controllers;

use App\Models\Recibos;
use App\Models\Devoluciones;
use App\Models\RecibosDetalles;
use App\Models\RecibosMedioPago;
use App\Models\RecibosMonedas;
use App\Models\NumeracionTickets;
use App\Models\Productos;
use App\Models\Ubigeo;
use App\Models\PuntoVenta;
use App\Models\SeriesTickets;
use App\Models\Monedas;
use App\Models\Clientes;
use App\Models\Cajas;
use App\Models\EfactLog;
use App\Models\Comprobantes;
use App\Models\ComprobantesDetalles;
use App\Models\TipoComprobante;
use App\Support\SunatAfectacionIgv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecibosMail;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ComprobanteIgvService;
use App\Services\EfactOseService;
use App\Services\JsonUblService;
use App\Services\EfactReciboVisorEnrichment;
use App\Services\EfactCorrelativoCpeService;

class RecibosController extends Controller
{
    public function index($id){

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

        $recibos = Recibos::where('id', $id)->orderBy('id', 'desc')->get()->load('puntoventa')->load('clientes')->load('monedas')->load('seriesList')->load('usuarios');
        $devoluciones = Devoluciones::where('idRecibos', $id)->get()->load('puntoventa')->load('recibos')->load('productos')->load('items');

        $data = array(
            'recibos' => $recibos,
            'devoluciones' => $devoluciones,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function show($id, Request $request){

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

        if($user->idRol == 1){
            if ($recibos = Recibos::where('idPuntoVenta', $id)->get()) {

                $recibos = Recibos::where('idPuntoVenta', $id)->orderBy('id', 'desc')->get();
                $recibosDetalles = RecibosDetalles::get()->load('recibos')->load('productos');
                $data = array(
                    'recibos' => $recibos,
                    'recibosDetalles' => $recibosDetalles,
                    'status' => 200
                );

            }else{
                $data = array(
                    'message' => 'Codigo no encontrado',
                    'status' => 404
                );
            }

        }else{

            if ($recibos = Recibos::where([['idPuntoVenta', $id], ['idUsuario', $user->id]])->get()) {

                $recibos = Recibos::where([['idPuntoVenta', $id], ['idUsuario', $user->id]])->orderBy('id', 'desc')->get();
                $recibosDetalles = RecibosDetalles::get()->load('recibos')->load('productos');
                $data = array(
                    'recibos' => $recibos,
                    'recibosDetalles' => $recibosDetalles,
                    'status' => 200
                );

            }else{
                $data = array(
                    'message' => 'Codigo no encontrado',
                    'status' => 404
                );
            }
        }

        return response()->json($data, $data['status']);
    }

    public function store(Request $request){

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

        $params = $request->all();

        if (isset($params['detalles']) && is_array($params['detalles']) && $params['detalles'] !== []) {
            /** @var ComprobanteIgvService $igvService */
            $igvService = app(ComprobanteIgvService::class);
            $params = $igvService->aplicarIgvAParams($params);
        }

        $resolved = $this->resolverSerieYNumeracionRecibo($params);
        if (! ($resolved['ok'] ?? false)) {
            return response()->json([
                'message' => $resolved['error'] ?? 'No se pudo resolver serie/numeración',
                'status' => 422,
            ], 422);
        }
        /** @var SeriesTickets $seriesTickets */
        $seriesTickets = $resolved['series'];
        /** @var NumeracionTickets|null $numeracionTickets */
        $numeracionTickets = $resolved['numeracion_ticket'] ?? null;
        $numeroCorrelativo = (int) ($resolved['numero'] ?? 1);

        // Ticket POS (interno): solo series / serieTicket / numeracionTicket / numeracion (correlativo caja).
        // Nunca usar serieComprobante*, serie_comprobante_efact ni numeracion con padding SUNAT como ticket.
        $seriePos = trim((string) ($params['serieTicket'] ?? ($params['series'] ?? '')));
        if ($seriePos === '') {
            $seriePos = trim((string) $seriesTickets->serie);
        }
        $numeracionPos = $numeroCorrelativo;
        if (isset($params['numeracionTicket']) && $params['numeracionTicket'] !== null && $params['numeracionTicket'] !== '') {
            $numeracionPos = max(1, (int) preg_replace('/\D+/', '', (string) $params['numeracionTicket']));
        } elseif (isset($params['numeracion']) && $params['numeracion'] !== null && $params['numeracion'] !== '') {
            $numeracionPos = max(1, (int) preg_replace('/\D+/', '', (string) $params['numeracion']));
        }

        $cpeDesdeParams = $this->extraerSerieNumeroCpeEfact($params);

        $monedas = Monedas::where('idPuntoVenta', $params['idPuntoVenta'] ?? null)->orderBy('id', 'asc')->first();
        $clientes = Clientes::first();
        $usuario = auth()->user();

        $emitirEfact = filter_var($params['emitirEfact'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $result = ['success' => false];
        $ticket = null;
        $efactEstado = 'NO_ENVIADO';
        $documentoGuardadoPath = null;
        $serieDocumento = null;
        $numeroDocumento = null;

        if ($emitirEfact) {
            /** @var EfactOseService $efact */
            $efact = app(EfactOseService::class);
            if ($cpeDesdeParams !== null) {
                $serieDocumento = $cpeDesdeParams['serie'];
                $numeroDocumento = $cpeDesdeParams['numero_int'];
            } else {
                // CPE para el JSON/XML: solo columnas explícitas de comprobante, no el correlativo POS.
                $serieDocumento = strtoupper(trim((string) ($params['serieComprobante'] ?? '')));
                if ($serieDocumento === '') {
                    return response()->json([
                        'message' => 'Para emitir eFact envíe el CPE en serie_comprobante_efact + numero_comprobante_efact (o serieComprobanteEfact + numeroComprobanteEfact), o bien serieComprobante + numeroComprobante del SUNAT. No use solo series del ticket POS.',
                        'status' => 422,
                    ], 422);
                }
                $numDocRaw = $params['numeroComprobante'] ?? null;
                if ($numDocRaw === null || $numDocRaw === '') {
                    return response()->json([
                        'message' => 'Para emitir eFact envíe el CPE en serie_comprobante_efact + numero_comprobante_efact (o serieComprobanteEfact + numeroComprobanteEfact), o bien serieComprobante + numeroComprobante. No use series/numeracion del ticket POS como correlativo SUNAT.',
                        'status' => 422,
                    ], 422);
                }
                $numeroDocumento = max(1, (int) preg_replace('/\D+/', '', (string) $numDocRaw));
            }
            $documentoEfact = [
                'file'     => $request->file('file'),
                'path'     => $params['json_path'] ?? ($params['xml_path'] ?? null),
                'content'  => $params['json_content'] ?? ($params['json'] ?? ($params['xml_content'] ?? ($params['xml'] ?? null))),
                'base64'   => $params['json_base64'] ?? ($params['xml_base64'] ?? null),
                'filename' => $params['json_filename'] ?? ($params['xml_filename'] ?? ($serieDocumento . '-' . $numeroDocumento . '.json')),
            ];

            // Auto-generar JSON+ si no viene archivo/contenido en el request.
            $tieneDocumento = $request->hasFile('file')
                || ! empty($documentoEfact['path'])
                || ! empty($documentoEfact['content'])
                || ! empty($documentoEfact['base64']);

            if (! $tieneDocumento) {
                /** @var JsonUblService $jsonService */
                $jsonService = app(JsonUblService::class);
                $jsonResult  = $jsonService->generarJson(
                    $params,
                    (string) $serieDocumento,
                    (int) $numeroDocumento
                );

                if (! ($jsonResult['success'] ?? false)) {
                    return response()->json([
                        'message' => 'Error al generar el JSON electrónico: ' . ($jsonResult['error'] ?? 'error desconocido'),
                        'status'  => 422,
                    ], 422);
                }

                $documentoEfact['content']  = $jsonResult['json'];
                $documentoEfact['filename'] = $jsonResult['filename'];
            }

            // Guardar copia local del documento electrónico para pruebas manuales.
            $documentoContenidoParaGuardar = null;
            if (is_string($documentoEfact['content'] ?? null) && trim($documentoEfact['content']) !== '') {
                $documentoContenidoParaGuardar = $documentoEfact['content'];
            } elseif (is_string($documentoEfact['path'] ?? null) && is_file($documentoEfact['path'])) {
                $contenidoDesdeRuta = @file_get_contents($documentoEfact['path']);
                if ($contenidoDesdeRuta !== false) {
                    $documentoContenidoParaGuardar = $contenidoDesdeRuta;
                }
            } elseif (is_string($documentoEfact['base64'] ?? null) && trim($documentoEfact['base64']) !== '') {
                $documentoContenidoParaGuardar = base64_decode($documentoEfact['base64'], true) ?: null;
            } elseif ($request->hasFile('file')) {
                $uploadedPath = $request->file('file')->getRealPath();
                if ($uploadedPath) {
                    $contenidoSubido = @file_get_contents($uploadedPath);
                    if ($contenidoSubido !== false) {
                        $documentoContenidoParaGuardar = $contenidoSubido;
                    }
                }
            }

            if (is_string($documentoContenidoParaGuardar) && trim($documentoContenidoParaGuardar) !== '') {
                $nombreDocumento = (string) ($documentoEfact['filename'] ?? ('documento-' . time() . '.json'));
                $rutaRelativa = 'efact_documentos/' . date('Ymd') . '/' . $nombreDocumento;
                Storage::disk('local')->put($rutaRelativa, $documentoContenidoParaGuardar);
                $documentoGuardadoPath = storage_path('app/' . $rutaRelativa);
            }

            $result  = $efact->enviarDocumento($documentoEfact);
            if (($result['error_code'] ?? null) && str_starts_with($result['error_code'], 'document_')) {
                return response()->json([
                    'message' => $result['error'] ?? 'No se pudo preparar el documento JSON para eFact',
                    'status' => $result['status'] ?? 422,
                    'documento_guardado_path' => $documentoGuardadoPath,
                    'xml_guardado_path' => $documentoGuardadoPath,
                ], $result['status'] ?? 422);
            }

            $ticket  = $result['ticket'] ?? null;
            $efactEstado = ($result['success'] ?? false) ? 'ENVIADO' : 'ERROR';
        }

        // CPE persistido: explícito en body o, si no, el usado al emitir con éxito (compatibilidad).
        $cpePersist = $cpeDesdeParams;
        if ($cpePersist === null && $emitirEfact && ($result['success'] ?? false)
            && $serieDocumento !== null && $serieDocumento !== '' && $numeroDocumento !== null) {
            $cpePersist = [
                'serie' => strtoupper(trim((string) $serieDocumento)),
                'numero_int' => max(1, (int) $numeroDocumento),
            ];
        }

        DB::beginTransaction();
        try {
            $tipoComprobante = null;
            if (! empty($params['tipoComprobante'])) {
                $tipoComprobante = TipoComprobante::where('documento', $params['tipoComprobante'])
                    ->orWhere('documento', 'like', '%'.$params['tipoComprobante'].'%')
                    ->first();
            }

            $recibos = new Recibos();
            $recibos->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $recibos->puntoventa = $params['puntoventa'] ?? null;
            $recibos->idCliente = $clientes->id ?? null;
            $recibos->documento = $clientes->numeroDoi ?? ($params['numeroDocumento'] ?? ($params['documento'] ?? null));
            $recibos->razonSocial = $clientes->nombre ?? ($params['cliente'] ?? ($params['razonSocial'] ?? null));
            $recibos->correo = $clientes->correo ?? ($params['correo'] ?? null);
            $recibos->idUsuario = $usuario->id;
            $recibos->vendedor = $usuario->nombre;
            $recibos->idSeries = $seriesTickets->id;
            $recibos->series = $seriePos;
            $recibos->numeracion = $numeracionPos;
            $recibos->fechaEmision = $params['fechaEmision'] ?? null;
            $recibos->idMoneda = $monedas->id ?? null;
            $recibos->moneda = $monedas->abreviatura ?? null;
            $recibos->tipoCambio = $params['tipoCambio'] ?? 1;
            $recibos->porcentajeDesc = $params['porcentajeDesc'] ?? null;
            $recibos->montoDesc = $params['montoDesc'] ?? null;
            $recibos->totalGravada = $params['totalGravada'] ?? null;
            $recibos->totalIgv = $params['totalIgv'] ?? null;
            $recibos->otrosCargo = $params['otrosCargo'] ?? null;
            $recibos->total = $params['total'] ?? null;
            $recibos->pagado = $params['pagado'] ?? null;
            $recibos->vuelto = $params['vuelto'] ?? null;
            $recibos->status = $params['status'] ?? null;
            if (Schema::hasColumn('tbl_recibos', 'emitirEfact')) {
                $recibos->emitirEfact = $emitirEfact;
            }

            if (Schema::hasColumn('tbl_recibos', 'efact_ticket')) {
                $recibos->efact_ticket = $emitirEfact ? $ticket : null;
            }
            if (Schema::hasColumn('tbl_recibos', 'efact_estado')) {
                $recibos->efact_estado = $efactEstado;
            }
            if ($cpePersist !== null) {
                $cpeSerie = $cpePersist['serie'];
                $cpeNumInt = $cpePersist['numero_int'];
                $cpeNumPad = str_pad((string) $cpeNumInt, 8, '0', STR_PAD_LEFT);
                if (Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie')) {
                    $recibos->efact_comprobante_serie = $cpeSerie;
                }
                if (Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero')) {
                    $recibos->efact_comprobante_numero = $cpeNumPad;
                }
            }

            $recibos->save();

            $idTipoCambio = $params['idTipoCambio'] ?? 1;
            if (empty($idTipoCambio)) {
                $idTipoCambio = 1;
            }

            $serieFact = $cpePersist !== null ? $cpePersist['serie'] : $seriePos;
            $numeroFact = $cpePersist !== null ? $cpePersist['numero_int'] : $numeracionPos;

            $facturacionData = [
                'idPuntoVenta'      => $params['idPuntoVenta'] ?? null,
                'puntoVenta'        => $params['puntoventa'] ?? null,
                'idSerie'           => $seriesTickets->id ?? null,
                'serie'             => $serieFact,
                'idNumeracion'      => $numeracionTickets->id ?? null,
                'numeracion'        => $numeroFact,
                'idTipoComprobante' => $tipoComprobante ? $tipoComprobante->id : null,
                'tipo'              => $params['tipoComprobante'] ?? null,
                'fecha'             => $params['fechaEmision'] ?? null,
                'numero'            => $numeroFact,
                'cliente'           => $params['cliente'] ?? ($params['razonSocial'] ?? null),
                'direccion'         => $params['direccion'] ?? null,
                'celular'           => $params['celular'] ?? null,
                'correo'            => $params['correo'] ?? null,
                'total'             => $params['total'] ?? null,
                'idTipoCambio'      => $idTipoCambio,
                'tipoCambio'        => $params['tipoCambio'] ?? 1,
                'igv'               => $params['totalIgv'] ?? null,
                'subTotal'          => $params['totalGravada'] ?? null,
                'idMoneda'          => $monedas->id ?? null,
                'emitirEfact'       => $emitirEfact,
                'efact_ticket'      => $emitirEfact ? $ticket : null,
                'efact_estado'      => $efactEstado,
            ];
            if ($cpePersist !== null) {
                $cpeSerieF = $cpePersist['serie'];
                $cpeNumPadF = str_pad((string) $cpePersist['numero_int'], 8, '0', STR_PAD_LEFT);
                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                    $facturacionData['efact_comprobante_serie'] = $cpeSerieF;
                }
                if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
                    $facturacionData['efact_comprobante_numero'] = $cpeNumPadF;
                }
            }

            $facturacion = Comprobantes::create($facturacionData);

            if (isset($params['detalles']) && is_array($params['detalles'])) {
                $idsProductos = collect($params['detalles'])
                    ->pluck('idProducto')
                    ->filter(fn ($pid) => $pid !== null && $pid !== '')
                    ->map(fn ($pid) => (int) $pid)
                    ->unique()
                    ->values();

                $productosPorId = $idsProductos->isEmpty()
                    ? collect()
                    : Productos::query()
                        ->whereIn('id', $idsProductos->all())
                        ->get()
                        ->keyBy('id');

                foreach ($params['detalles'] as $value) {
                    $recibosDetalles = new RecibosDetalles();
                    $recibosDetalles->idRecibo = $recibos->id;
                    $recibosDetalles->idProducto = $value['idProducto'] ?? null;
                    $recibosDetalles->codigoBarra = $value['codigoBarra'] ?? null;
                    $recibosDetalles->nombre = $value['nombre'] ?? null;
                    $recibosDetalles->precio = $value['precio'] ?? null;
                    $recibosDetalles->cantidad = $value['cantidad'] ?? null;
                    $recibosDetalles->subtotal = $value['subtotal'] ?? null;
                    $recibosDetalles->igv = $value['igv'] ?? null;
                    $recibosDetalles->total = $value['total'] ?? null;
                    $recibosDetalles->porcentajeDesc = $value['porcentajeDesc'] ?? null;
                    $recibosDetalles->totalDesc = $value['totalDesc'] ?? null;

                    $idProd = isset($value['idProducto']) ? (int) $value['idProducto'] : null;
                    $productos = $idProd ? $productosPorId->get($idProd) : null;

                    if (Schema::hasColumn('tbl_recibo_detalles', 'codigo_afectacion_igv')) {
                        $recibosDetalles->codigo_afectacion_igv = SunatAfectacionIgv::resolveCodigo($value, $productos);
                    }

                    $recibosDetalles->precioCompra = $productos ? $productos->precioCompra : null;
                    $recibosDetalles->save();

                    if ($productos) {
                        $productos->stockActual = $productos->stockActual - ($value['cantidad'] ?? 0);
                        $productos->save();
                    }

                    ComprobantesDetalles::create([
                        'idComprobante' => $facturacion->id,
                        'idProducto'    => $value['idProducto'] ?? null,
                        'cantidad'      => $value['cantidad'] ?? null,
                        'precioUnitario'=> $value['precio'] ?? null,
                        'subtotal'      => $value['subtotal'] ?? null,
                        'igv'           => $value['igv'] ?? null,
                        'total'         => $value['total'] ?? null,
                    ]);
                }
            }

            if (isset($params['medioPagos']) && is_array($params['medioPagos'])) {
                $recibosMedioPago = new RecibosMedioPago();
                $recibosMedioPago->idRecibo = $recibos->id;
                $recibosMedioPago->idMedioPago = $params['medioPagos']['idMedioPago'] ?? null;
                $recibosMedioPago->importe = $params['medioPagos']['importe'] ?? null;
                $recibosMedioPago->nota = $params['medioPagos']['nota'] ?? null;
                $recibosMedioPago->save();
            }

            $recibosMonedas = new RecibosMonedas();
            $recibosMonedas->idRecibo = $recibos->id;
            $recibosMonedas->idMoneda = $recibos->idMoneda;
            $recibosMonedas->moneda = $recibos->moneda;
            $recibosMonedas->tipoCambio = $recibos->tipoCambio;
            $recibosMonedas->save();

            if ($numeracionTickets) {
                $recibosNumeracion = NumeracionTickets::where('id', $numeracionTickets->id)->lockForUpdate()->first();
                if ($recibosNumeracion) {
                    // Último correlativo POS = máximo entre lo enviado y lo ya persistido en recibos (evita +1 fantasma en tbl_numeracion_tickets).
                    $serieTrim = trim((string) $seriePos);
                    $idPvRec = (int) ($recibos->idPuntoVenta ?? 0);
                    $maxRecibo = $idPvRec > 0
                        ? Recibos::maxNumeracionParaSeriePuntoVenta($idPvRec, $serieTrim)
                        : 0;
                    $sync = max($numeracionPos, $maxRecibo);
                    $recibosNumeracion->numeroActual = $sync;
                    $recibosNumeracion->save();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al guardar recibo',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        if ($emitirEfact) {
            try {
                EfactLog::create([
                    'idComprobante'  => $recibos->id,
                    'ticket'         => $ticket,
                    'tipo_operacion' => 'enviar_recibo',
                    'response_json'  => json_encode($result['body'] ?? []),
                    'status_code'    => $result['status'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // No bloquea la venta si falla el registro de log.
            }
        }

        if ($emitirEfact && ! ($result['success'] ?? false)) {
            $recibos->refresh();
            $emitPayload = $this->payloadComprobanteEmitidoEfact($cpePersist);
            $ticketOut = trim((string) ($recibos->efact_ticket ?? $ticket ?? ''));
            $ticketOut = $ticketOut !== '' ? $ticketOut : null;
            $aliasTicket = $this->aliasesTicketOse($ticketOut);

            return response()->json(array_merge([
                'recibos' => array_merge($recibos->toArray(), [
                    'efact_ticket' => $ticketOut,
                ], $emitPayload, $aliasTicket),
                'comprobante' => $facturacion,
                'message' => 'Ticket guardado, pero hubo error al emitir en eFact',
                'efact_error' => $result['error'] ?? null,
                'efact_response' => $result['body'] ?? null,
                'documento_guardado_path' => $documentoGuardadoPath,
                'xml_guardado_path' => $documentoGuardadoPath,
                'efact_ticket' => $ticketOut,
                'efact_pdf_disponible' => false,
                'status' => 207,
            ], $emitPayload, $aliasTicket), 207);
        }

        $mensaje = 'Se ha generado el Ticket ' . $recibos->series . '-' . $recibos->numeracion;
        if (! $emitirEfact) {
            $mensaje .= ' (sin envío a eFact)';
        }

        $recibos->refresh();
        $emitPayload = $this->payloadComprobanteEmitidoEfact($cpePersist);
        $ticketOut = $emitirEfact ? trim((string) ($recibos->efact_ticket ?? $ticket ?? '')) : '';
        $ticketOut = ($ticketOut !== '' && $emitirEfact) ? $ticketOut : null;
        $aliasTicket = $this->aliasesTicketOse($ticketOut);

        $data = array_merge([
            'recibos' => array_merge($recibos->toArray(), [
                'efact_ticket' => $ticketOut,
            ], $emitPayload, $aliasTicket),
            'comprobante' => $facturacion,
            'message' => $mensaje,
            'efact_emitido' => $emitirEfact,
            'efact_ticket' => $ticketOut,
            'efact_response' => $emitirEfact ? ($result['body'] ?? null) : null,
            'efact_pdf_disponible' => $emitirEfact && ($result['success'] ?? false),
            'documento_guardado_path' => $documentoGuardadoPath,
            'xml_guardado_path' => $documentoGuardadoPath,
            'status' => 200,
        ], $emitPayload, $aliasTicket);

        return response()->json($data, $data['status']);
    }

    public function update($id, Request $request){

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

        $params = $request->all();

        if($recibos = Recibos::find($id)){
            $recibos->total = $params['total'] ?? $recibos->total;

            if (isset($params['detalles']) && is_array($params['detalles'])) {
                foreach ($params['detalles'] as $value) {
                    $productos = Productos::find($value['idProducto'] ?? null);

                    if (($value['id'] ?? 0) == 0) {
                        $recibosDetalles = new RecibosDetalles();
                        $recibosDetalles->idRecibo = $recibos->id;
                        $recibosDetalles->idProducto = $value['idProducto'] ?? null;
                        $recibosDetalles->codigoBarra = $value['codigoBarra'] ?? null;
                        $recibosDetalles->nombre = $value['nombre'] ?? null;
                        $recibosDetalles->precio = $value['precio'] ?? null;
                        $recibosDetalles->cantidad = $value['cantidad'] ?? null;
                        $recibosDetalles->subtotal = $value['subtotal'] ?? null;
                        $recibosDetalles->igv = $value['igv'] ?? null;
                        $recibosDetalles->total = $value['total'] ?? null;
                        $recibosDetalles->porcentajeDesc = 0.00;
                        $recibosDetalles->totalDesc = 0.00;

                        if (Schema::hasColumn('tbl_recibo_detalles', 'codigo_afectacion_igv')) {
                            $recibosDetalles->codigo_afectacion_igv = SunatAfectacionIgv::resolveCodigo($value, $productos);
                        }

                        $recibosDetalles->save();
                    }

                    if ($productos) {
                        $productos->stockActual = $productos->stockActual - ($value['cantidad'] ?? 0);
                        $productos->save();
                    }
                }
            }

            $recibos->save();

            $data = array(
                'recibos' => $recibos,
                'message' => 'El Ticket ' . $recibos->series . '-' . $recibos->numeracion . ' fue actualizado',
                'status' => 200
            );

        }else{

            $data = array(
                'message' => 'Codigo no encontrado',
                'status' => 404
            );
        }

        return response()->json($data, $data['status']);
    }

    public function destroy($id, Request $request){

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

        if($recibos = Recibos::find($id)){

            // $recibosMedioPago = RecibosMedioPago::where('idRecibo', $id)->get();
            // foreach ($recibosMedioPago as $value) {
            //     $value->delete();
            // }

            // $recibosMonedas = RecibosMonedas::where('idRecibo', $id)->get();
            // foreach ($recibosMonedas as $key => $value) {
            //     $value->delete();
            // }

            // $recibosDetalles = RecibosDetalles::where('idRecibo', $id)->get();
            // foreach ($recibosDetalles as $key => $value) {
            //     $value->delete();
            // }

            // $recibos->delete();

            $recibos->status = 0;
            $recibos->save();

            $data = array(
                'recibos' => $recibos,
                'message' => 'Ticket eliminado correctamente.',
                'status' => 200
            );

        }else{
            $data = array(
                'message' => 'Codigo no encontrado',
                'status' => 404
            );
        }

        return response()->json($data, $data['status']);
    }

    public function reporteRecibos($id){
        $recibos = Recibos::find($id)->load('puntoventa')->load('clientes')->load('monedas')->load('seriesList')->load('usuarios')->load('detalles');
        $medioPago = RecibosMedioPago::where('idRecibo', $recibos->id)->first();
        $puntoVenta = PuntoVenta::find($recibos->idPuntoVenta);
        $ubigeo = Ubigeo::find($puntoVenta->idUbigeo);
        $cajas = Cajas::where('idPuntoVenta', $recibos->idPuntoVenta)->first();
        $cantidad = 0;
        $vuelto = $medioPago->importe - $recibos->total;

        foreach ($recibos->detalles as $value) {
            $cantidad = $cantidad + $value->cantidad;
        }
        $pdf = PDF::loadView('recibos', compact('recibos', 'ubigeo', 'puntoVenta', 'cantidad', 'medioPago', 'vuelto', 'cajas'));

        $GLOBALS['bodyHeight'] = 0;

        $pdf->setCallbacks([
            'myCallbacks' => [
                'event' => 'end_frame',
                'f' => function ($frame) {
                    $node = $frame->get_node();

                    if (strtolower($node->nodeName) === "body") {
                        $padding_box = $frame->get_padding_box();
                        $GLOBALS['bodyHeight'] += $padding_box['h'];
                    }
                }
            ]
        ]);

        $docHeight = $GLOBALS['bodyHeight'] + 800;
        $pdf->setPaper([0,0,227, $docHeight]);
        // $pdf->setPaper('b7', 'portrait');
        return $pdf->download('TICKET-' . $recibos->numeracion . '.pdf');
        // return $pdf->stream();
    }

    public function enviarCorreo(Request $request){
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

    $params = $request->all();

    Mail::to($params['correo'] ?? null)->send(new RecibosMail($params));
    }

    public function buscarPorFecha(Request $request){

        date_default_timezone_set('America/Lima'); // o la que corresponda

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

        $params = $request->all();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $recibos = Recibos::where([['idPuntoVenta', $params['idPuntoVenta'] ?? null], ['status', 1]])
                    ->whereDate('created_at', '>=', $params['fechaInicio'] ?? null)
                    ->whereDate('created_at', '<=', $params['fechaFin'] ?? null)
                    ->orderByDesc('created_at')
                    ->get();
                    // ->paginate($perPage, ['*'], 'page', $page);

        $recibosBuscar = Recibos::select('id')->where([['idPuntoVenta', $params['idPuntoVenta'] ?? null], ['status', 1]])
                    ->whereDate('created_at', '>=', $params['fechaInicio'] ?? null)
                    ->whereDate('created_at', '<=', $params['fechaFin'] ?? null)
                    ->orderByDesc('created_at')
                    ->get();
        $recibosDetalles = RecibosDetalles::whereIn('idRecibo', $recibosBuscar)->get();

        $enrichment = app(EfactReciboVisorEnrichment::class);
        $recibosConEfact = $recibos->map(function (Recibos $r) use ($enrichment) {
            return array_merge($r->toArray(), $enrichment->camposParaListado($r));
        })->values();

        $data = array(
            'recibos' => $recibosConEfact,
            'recibosDetalles' => $recibosDetalles,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    /**
     * Siguiente numeración para recibos.
     *
     * - Sin `serieComprobante` / `serie_comprobante_efact`: correlativo **ticket POS** (caja), vía series/serieTicket.
     * - Con `serieComprobante` (p. ej. BE01): correlativo **CPE SUNAT** siguiente, mirando tbl_facturacion + tbl_recibos eFact.
     *
     * Query: idPuntoVenta (req), tipoComprobante?, serieComprobante?, series?, serieTicket?, …
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

        $params = $request->all();
        $serieCpe = trim((string) ($params['serieComprobante'] ?? $params['serie_comprobante_efact'] ?? ''));

        if ($serieCpe !== '') {
            $idPv = $params['idPuntoVenta'] ?? null;
            if ($idPv === null || $idPv === '') {
                return response()->json(['message' => 'idPuntoVenta es requerido', 'status' => 422], 422);
            }
            $idPvInt = (int) $idPv;
            $serieNorm = strtoupper($serieCpe);

            /** @var EfactCorrelativoCpeService $cpeCorr */
            $cpeCorr = app(EfactCorrelativoCpeService::class);
            $ultimoEmitidoBd = $cpeCorr->maxUltimoEmitidoPorSerie($serieNorm, $idPvInt);
            if ($ultimoEmitidoBd === 0) {
                $ultimoEmitidoBd = $cpeCorr->maxUltimoEmitidoPorSerie($serieNorm, null);
            }

            $record = SeriesTickets::query()
                ->where('idPuntoVenta', $idPvInt)
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
            $siguiente = max($ultimoEmitidoBd + 1, $desdeNumerador, 1);

            return response()->json([
                'serie' => $serieNorm,
                'idSerie' => $record ? (int) $record->id : null,
                'idNumeracion' => $numer ? (int) $numer->id : null,
                'siguiente' => $siguiente,
                'alcance' => 'correlativo_cpe_sunat',
                'ultimo_correlativo_emitido_bd' => $ultimoEmitidoBd,
                'numeroActual_bd' => $desdeNumerador,
                'numeroActual_numerador' => $desdeNumerador,
                'numeroActual_es_siguiente_correlativo_disponible' => true,
                'siguiente_es_correlativo_a_emitir' => true,
                'status' => 200,
            ], 200);
        }

        $resolved = $this->resolverSerieYNumeracionRecibo($params);
        if (! ($resolved['ok'] ?? false)) {
            return response()->json([
                'message' => $resolved['error'] ?? 'No se pudo resolver numeración',
                'status' => 422,
            ], 422);
        }

        /** @var SeriesTickets $serie */
        $serie = $resolved['series'];
        /** @var NumeracionTickets|null $num */
        $num = $resolved['numeracion_ticket'] ?? null;

        return response()->json([
            'serie' => (string) $serie->serie,
            'idSerie' => (int) $serie->id,
            'idNumeracion' => $num ? (int) $num->id : null,
            'siguiente' => (int) ($resolved['numero'] ?? 1),
            'alcance' => 'ticket_pos_caja',
            'numeroActual_bd' => $num ? (int) ($num->numeroActual ?? 0) : null,
            'numeroActual_es_ultimo_correlativo_usado' => true,
            'numeroActual_es_siguiente_correlativo_disponible' => false,
            'status' => 200,
        ], 200);
    }

    /**
     * Resuelve serie y correlativo para recibos usando el último número real en BD.
     *
     * @param  array<string,mixed>  $params
     * @return array{ok:bool,series?:SeriesTickets,numeracion_ticket?:NumeracionTickets|null,numero?:int,error?:string}
     */
    private function resolverSerieYNumeracionRecibo(array $params): array
    {
        $idPv = $params['idPuntoVenta'] ?? null;
        if ($idPv === null || $idPv === '') {
            return ['ok' => false, 'error' => 'idPuntoVenta es requerido'];
        }

        // Solo serie del ticket POS; no usar serieComprobante* (SUNAT).
        $serieSolicitada = trim((string) ($params['serieTicket'] ?? ($params['series'] ?? '')));
        $tipo = strtoupper(trim((string) ($params['tipoComprobante'] ?? '')));

        $sq = SeriesTickets::query()->where('idPuntoVenta', $idPv);
        if ($serieSolicitada !== '') {
            $sq->where('serie', $serieSolicitada);
        } elseif ($tipo !== '') {
            $pref = str_contains($tipo, 'FACT') || $tipo === '01' ? 'F' : 'B';
            $sq->where('serie', 'like', $pref . '%');
        }
        $seriesTickets = $sq->orderBy('id', 'asc')->first();
        if (! $seriesTickets) {
            $seriesTickets = SeriesTickets::where('idPuntoVenta', $idPv)->orderBy('id', 'asc')->first();
        }
        if (! $seriesTickets) {
            return ['ok' => false, 'error' => 'No existe serie configurada para el punto de venta'];
        }

        $numeracion = NumeracionTickets::where('idSeriesTickets', $seriesTickets->id)->orderBy('id', 'asc')->first();

        $serie = trim((string) $seriesTickets->serie);
        $ultimoRecibo = Recibos::maxNumeracionParaSeriePuntoVenta((int) $idPv, $serie);

        // No mezclar tbl_facturacion (puede llevar serie/número del CPE SUNAT) con el correlativo del ticket POS.
        // `numeroActual` = último correlativo POS usado (ver docs). Compat: si aún está en BD como antiguo "siguiente" (= R+1), se trata como R.
        $d = $numeracion ? (int) ($numeracion->numeroActual ?? 0) : 0;
        $ultimoNumerador = 0;
        if ($numeracion) {
            $ultimoNumerador = ($d === $ultimoRecibo + 1) ? $ultimoRecibo : $d;
        }
        $base = max($ultimoRecibo, $ultimoNumerador);
        $numero = max(1, $base + 1);

        if (! empty($params['numeracionTicket']) || ! empty($params['numeracion'])) {
            $manual = (int) ($params['numeracionTicket'] ?? $params['numeracion']);
            if ($manual > 0) {
                $numero = $manual;
            }
        }

        return [
            'ok' => true,
            'series' => $seriesTickets,
            'numeracion_ticket' => $numeracion,
            'numero' => $numero,
        ];
    }

    /**
     * Alias del UUID OSE (GET /api/efact/pdf?ticket=… acepta los mismos nombres en query).
     *
     * @return array{ose_ticket: ?string, ticket_ose: ?string, efactTicket: ?string}
     */
    private function aliasesTicketOse(?string $ticket): array
    {
        $t = $ticket !== null ? trim($ticket) : '';
        if ($t === '') {
            return ['ose_ticket' => null, 'ticket_ose' => null, 'efactTicket' => null];
        }

        return ['ose_ticket' => $t, 'ticket_ose' => $t, 'efactTicket' => $t];
    }

    /**
     * Texto CPE SUNAT para respuesta al POS (no confundir con series/numeracion del ticket).
     *
     * @param  array{serie:string,numero_int:int}|null  $cpePersist
     * @return array{comprobante_emitido: ?string, comprobante_electronico: ?array{serie:string,numero:string,comprobante:string}}
     */
    private function payloadComprobanteEmitidoEfact(?array $cpePersist): array
    {
        if ($cpePersist === null) {
            return ['comprobante_emitido' => null, 'comprobante_electronico' => null];
        }
        $s = $cpePersist['serie'];
        $nPad = str_pad((string) $cpePersist['numero_int'], 8, '0', STR_PAD_LEFT);
        $comp = $s . '-' . $nPad;

        return [
            'comprobante_emitido' => $comp,
            'comprobante_electronico' => [
                'serie' => $s,
                'numero' => $nPad,
                'comprobante' => $comp,
            ],
        ];
    }

    /**
     * Serie y número del comprobante electrónico (SUNAT), separados del ticket POS.
     *
     * @param  array<string,mixed>  $params
     * @return array{serie:string,numero_int:int}|null
     */
    private function extraerSerieNumeroCpeEfact(array $params): ?array
    {
        $serie = $params['serie_comprobante_efact']
            ?? $params['serieComprobanteEfact']
            ?? $params['serieComprobante_efact']
            ?? null;
        $num = $params['numero_comprobante_efact']
            ?? $params['numeroComprobanteEfact']
            ?? $params['numeroComprobante_efact']
            ?? null;

        if (($serie === null || $serie === '') || ($num === null || $num === '')) {
            return null;
        }

        $serieNorm = strtoupper(trim((string) $serie));
        $numInt = max(1, (int) preg_replace('/\D+/', '', (string) $num));

        return ['serie' => $serieNorm, 'numero_int' => $numInt];
    }
}
