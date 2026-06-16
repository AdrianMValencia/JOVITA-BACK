<?php

namespace App\Http\Controllers;

use App\Services\InventarioValorizadoSunatService;
use App\Services\KardexGeneralService;
use App\Services\RceSireComprasService;
use App\Services\RvieSireVentasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ContabilidadController extends Controller
{
    /**
     * Listado JSON del reporte RCE SIRE – Registro de compras electrónico (40 columnas por fila, plantilla oficial).
     *
     * Query: fechaInicio, fechaFin (Y-m-d), idPuntoVenta (opcional), solo_activas (opcional, default 1).
     */
    public function rceComprasListado(Request $request, RceSireComprasService $rce)
    {
        try {
            if (! JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $fechaInicio = (string) $request->query('fechaInicio', '');
        $fechaFin = (string) $request->query('fechaFin', '');
        if ($fechaInicio === '' || $fechaFin === '') {
            return response()->json([
                'message' => 'Parámetros fechaInicio y fechaFin son obligatorios (formato Y-m-d).',
                'status' => 422,
            ], 422);
        }

        $idPv = $request->query('idPuntoVenta');
        $idPvInt = ($idPv !== null && $idPv !== '') ? (int) $idPv : null;
        $soloActivas = filter_var($request->query('solo_activas', '1'), FILTER_VALIDATE_BOOLEAN);

        try {
            $compras = $rce->queryCompras($idPvInt, $fechaInicio, $fechaFin, $soloActivas);
            $filas = $rce->buildFilas($compras);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo armar el reporte RCE compras.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'reporte' => 'PROPUESTA REGISTRO DE COMPRAS ELECTRÓNICO - RCE SIRE SUNAT',
            'cabeceras' => RceSireComprasService::CABECERAS_RCE,
            'periodo' => [
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'idPuntoVenta' => $idPvInt,
                'solo_activas' => $soloActivas,
            ],
            'total_registros' => count($filas),
            'filas' => $filas,
            'status' => 200,
        ], 200);
    }

    /**
     * Genera Excel a partir del template COMPRAS.xlsx con las mismas filas que el listado.
     *
     * Query: mismos que rceComprasListado.
     */
    public function rceComprasExcel(Request $request, RceSireComprasService $rce): BinaryFileResponse|JsonResponse
    {
        try {
            if (! JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        if (! class_exists(IOFactory::class)) {
            return response()->json([
                'message' => 'Falta la librería PhpSpreadsheet. Ejecute en la carpeta backend: composer update (o composer require phpoffice/phpspreadsheet).',
                'status' => 503,
            ], 503);
        }

        if (! extension_loaded('zip') || ! class_exists(\ZipArchive::class)) {
            return response()->json([
                'message' => 'PHP requiere la extensión zip (ZipArchive) para archivos .xlsx.',
                'detalle' => 'En XAMPP: abra php.ini (suele ser C:\\xampp\\php\\php.ini), busque la línea ;extension=zip y cámbiela a extension=zip (sin punto y coma), guarde y reinicie Apache o el proceso de artisan serve. Verifique con: php -m (debe listarse "zip").',
                'status' => 503,
            ], 503);
        }

        $fechaInicio = (string) $request->query('fechaInicio', '');
        $fechaFin = (string) $request->query('fechaFin', '');
        if ($fechaInicio === '' || $fechaFin === '') {
            return response()->json([
                'message' => 'Parámetros fechaInicio y fechaFin son obligatorios (formato Y-m-d).',
                'status' => 422,
            ], 422);
        }

        $idPv = $request->query('idPuntoVenta');
        $idPvInt = ($idPv !== null && $idPv !== '') ? (int) $idPv : null;
        $soloActivas = filter_var($request->query('solo_activas', '1'), FILTER_VALIDATE_BOOLEAN);

        $nombre = 'RCE_COMPRAS_'.preg_replace('/[^0-9\-]/', '', $fechaInicio).'_'.preg_replace('/[^0-9\-]/', '', $fechaFin).'.xlsx';

        $tmpPath = null;
        try {
            $compras = $rce->queryCompras($idPvInt, $fechaInicio, $fechaFin, $soloActivas);
            $filas = $rce->buildFilas($compras);
            $celdasPorFila = array_map(static fn (array $f) => $f['celdas'], $filas);
            $spreadsheet = $rce->fillTemplateSpreadsheet($celdasPorFila);
            $spreadsheet->garbageCollect();

            $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rce_compras_'.uniqid('', true).'.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save($tmpPath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
        } catch (\RuntimeException $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
            $msg = $e->getMessage();
            $status = (stripos($msg, 'zip') !== false || stripos($msg, 'ZipArchive') !== false) ? 503 : 422;

            return response()->json([
                'message' => $msg,
                'status' => $status,
            ], $status);
        } catch (\Throwable $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }

            $payload = [
                'message' => 'Error al generar el Excel RCE compras.',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
            if (config('app.debug')) {
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }

            return response()->json($payload, 500);
        }

        return response()->download($tmpPath, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Listado JSON del reporte RVIE SIRE – Registro de ventas electrónico (40 columnas, plantilla oficial).
     *
     * Query: fechaInicio, fechaFin (Y-m-d), idPuntoVenta (opcional), solo_activas (opcional, default 1).
     */
    public function rvieVentasListado(Request $request, RvieSireVentasService $rvie): JsonResponse
    {
        try {
            if (! JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $fechaInicio = (string) $request->query('fechaInicio', '');
        $fechaFin = (string) $request->query('fechaFin', '');
        if ($fechaInicio === '' || $fechaFin === '') {
            return response()->json([
                'message' => 'Parámetros fechaInicio y fechaFin son obligatorios (formato Y-m-d).',
                'status' => 422,
            ], 422);
        }

        $idPv = $request->query('idPuntoVenta');
        $idPvInt = ($idPv !== null && $idPv !== '') ? (int) $idPv : null;
        $soloActivas = filter_var($request->query('solo_activas', '1'), FILTER_VALIDATE_BOOLEAN);

        try {
            $recibos = $rvie->queryRecibos($idPvInt, $fechaInicio, $fechaFin, $soloActivas);
            $filas = $rvie->buildFilas($recibos);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo armar el reporte RVIE ventas.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        return response()->json([
            'reporte' => 'PROPUESTA REGISTRO DE VENTAS ELECTRÓNICO - RVIE SIRE SUNAT',
            'cabeceras' => RvieSireVentasService::CABECERAS_RVIE,
            'periodo' => [
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'idPuntoVenta' => $idPvInt,
                'solo_activas' => $soloActivas,
            ],
            'total_registros' => count($filas),
            'filas' => $filas,
            'status' => 200,
        ], 200);
    }

    /**
     * Excel RVIE a partir de VENTAS.xlsx (mismas filas que el listado).
     */
    public function rvieVentasExcel(Request $request, RvieSireVentasService $rvie): BinaryFileResponse|JsonResponse
    {
        try {
            if (! JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        if (! class_exists(IOFactory::class)) {
            return response()->json([
                'message' => 'Falta la librería PhpSpreadsheet.',
                'status' => 503,
            ], 503);
        }

        if (! extension_loaded('zip') || ! class_exists(\ZipArchive::class)) {
            return response()->json([
                'message' => 'PHP requiere la extensión zip (ZipArchive) para archivos .xlsx.',
                'status' => 503,
            ], 503);
        }

        $fechaInicio = (string) $request->query('fechaInicio', '');
        $fechaFin = (string) $request->query('fechaFin', '');
        if ($fechaInicio === '' || $fechaFin === '') {
            return response()->json([
                'message' => 'Parámetros fechaInicio y fechaFin son obligatorios (formato Y-m-d).',
                'status' => 422,
            ], 422);
        }

        $idPv = $request->query('idPuntoVenta');
        $idPvInt = ($idPv !== null && $idPv !== '') ? (int) $idPv : null;
        $soloActivas = filter_var($request->query('solo_activas', '1'), FILTER_VALIDATE_BOOLEAN);

        $nombre = 'RVIE_VENTAS_'.preg_replace('/[^0-9\-]/', '', $fechaInicio).'_'.preg_replace('/[^0-9\-]/', '', $fechaFin).'.xlsx';

        $tmpPath = null;
        try {
            $recibos = $rvie->queryRecibos($idPvInt, $fechaInicio, $fechaFin, $soloActivas);
            $filas = $rvie->buildFilas($recibos);
            $celdasPorFila = array_map(static fn (array $f) => $f['celdas'], $filas);
            $spreadsheet = $rvie->fillTemplateSpreadsheet($celdasPorFila);
            $spreadsheet->garbageCollect();

            $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rvie_ventas_'.uniqid('', true).'.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save($tmpPath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
        } catch (\RuntimeException $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
            $msg = $e->getMessage();
            $status = (stripos($msg, 'zip') !== false || stripos($msg, 'ZipArchive') !== false) ? 503 : 422;

            return response()->json([
                'message' => $msg,
                'status' => $status,
            ], $status);
        } catch (\Throwable $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }

            $payload = [
                'message' => 'Error al generar el Excel RVIE ventas.',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
            if (config('app.debug')) {
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }

            return response()->json($payload, 500);
        }

        return response()->download($tmpPath, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Listado JSON – Inventario valorizado SUNAT (kardex por producto, 14 columnas).
     *
     * Query: fechaInicio, fechaFin (Y-m-d), idPuntoVenta.
     * Producto opcional: idProducto, codigoBarra (exacto), codigo y/o nombre (parcial). Sin filtros = todos los productos del PV.
     * incluirSaldoInicial (opcional, default 1): si es 0, omite la fila SALDO INICIAL al inicio.
     * detalle (opcional, default 0): con varios productos, 1 incluye filas kardex (lento); 0 devuelve solo saldos por producto.
     * page, per_page (opcional): paginación del listado (default page=1, per_page=100, máx. 500).
     */
    public function inventarioValorizadoListado(Request $request, InventarioValorizadoSunatService $inventario): JsonResponse
    {
        $auth = $this->autenticarJwtContabilidad();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validacion = $this->validarParamsInventarioValorizado($request);
        if ($validacion instanceof JsonResponse) {
            return $validacion;
        }

        [$fechaInicio, $fechaFin, $idPvInt, $idProducto, $codigoBarra, $codigo, $nombre, $incluirSaldoInicial] = $validacion;

        $inventario->extendExecutionTime();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = $inventario->normalizarPerPage(
            ($request->query('per_page') !== null && $request->query('per_page') !== '')
                ? (int) $request->query('per_page')
                : null
        );

        $paginado = $inventario->resolveProductosPaginados(
            $idProducto,
            $codigoBarra,
            $codigo,
            $nombre,
            $idPvInt,
            $page,
            $perPage
        );
        $paginator = $paginado['paginator'];
        $productos = $paginado['productos'];

        if ($paginator->total() === 0) {
            return response()->json([
                'message' => 'No hay productos para el punto de venta y filtros indicados.',
                'status' => 404,
            ], 404);
        }

        $solicitaDetalle = filter_var($request->query('detalle', '0'), FILTER_VALIDATE_BOOLEAN);
        $incluirFilasDetalle = $paginator->total() === 1 || $solicitaDetalle;

        try {
            $reportes = $inventario->buildReportes(
                $productos,
                $idPvInt,
                $fechaInicio,
                $fechaFin,
                $incluirSaldoInicial,
                false,
                $incluirFilasDetalle
            );
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $esTimeout = stripos($msg, 'Maximum execution time') !== false
                || stripos($msg, 'max_execution_time') !== false;

            return response()->json([
                'message' => $esTimeout
                    ? 'El reporte tardó demasiado. Pruebe un rango de fechas más corto o filtre por producto.'
                    : 'No se pudo armar el inventario valorizado SUNAT.',
                'error' => $msg,
                'total_productos_solicitados' => $paginator->total(),
                'status' => $esTimeout ? 504 : 500,
            ], $esTimeout ? 504 : 500);
        }

        $paginacion = $inventario->metaPaginacion($paginator, count($reportes));

        if ($paginator->total() === 1) {
            $reporte = $reportes[0];

            return response()->json([
                'reporte' => 'INVENTARIO VALORIZADO SUNAT',
                'cabeceras' => InventarioValorizadoSunatService::CABECERAS,
                'modo' => 'producto_unico',
                'modo_detalle' => 'kardex',
                'paginacion' => $paginacion,
                'producto' => $reporte['producto'],
                'periodo' => $reporte['periodo'],
                'saldo_inicial' => $reporte['saldo_inicial'],
                'saldo_final' => $reporte['saldo_final'],
                'total_registros' => count($reporte['filas']),
                'filas' => $reporte['filas'],
                'status' => 200,
            ], 200);
        }

        $totalRegistros = 0;
        $items = [];
        foreach ($reportes as $reporte) {
            $totalRegistros += count($reporte['filas']);
            $item = [
                'producto' => $reporte['producto'],
                'saldo_inicial' => $reporte['saldo_inicial'],
                'saldo_final' => $reporte['saldo_final'],
                'total_registros' => count($reporte['filas']),
            ];
            if ($incluirFilasDetalle) {
                $item['filas'] = $reporte['filas'];
            }
            $items[] = $item;
        }

        return response()->json([
            'reporte' => 'INVENTARIO VALORIZADO SUNAT',
            'cabeceras' => InventarioValorizadoSunatService::CABECERAS,
            'modo' => 'todos_productos',
            'modo_detalle' => $incluirFilasDetalle ? 'kardex' : 'resumen',
            'paginacion' => $paginacion,
            'periodo' => $reportes[0]['periodo'],
            'total_productos' => $paginator->total(),
            'total_registros' => $totalRegistros,
            'productos' => $items,
            'status' => 200,
        ], 200);
    }

    /**
     * Excel inventario valorizado SUNAT (template INVENTARIO VALORIZADO SUNAT.xlsx, datos desde fila 6).
     * Ignora page/per_page: exporta todos los productos que coincidan con los filtros.
     */
    public function inventarioValorizadoExcel(Request $request, InventarioValorizadoSunatService $inventario): BinaryFileResponse|JsonResponse
    {
        $auth = $this->autenticarJwtContabilidad();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if (! class_exists(IOFactory::class)) {
            return response()->json([
                'message' => 'Falta la librería PhpSpreadsheet.',
                'status' => 503,
            ], 503);
        }

        if (! extension_loaded('zip') || ! class_exists(\ZipArchive::class)) {
            return response()->json([
                'message' => 'PHP requiere la extensión zip (ZipArchive) para archivos .xlsx.',
                'status' => 503,
            ], 503);
        }

        $validacion = $this->validarParamsInventarioValorizado($request);
        if ($validacion instanceof JsonResponse) {
            return $validacion;
        }

        [$fechaInicio, $fechaFin, $idPvInt, $idProducto, $codigoBarra, $codigo, $nombre, $incluirSaldoInicial] = $validacion;

        $inventario->extendExecutionTime();

        $productos = $inventario->resolveProductos($idProducto, $codigoBarra, $codigo, $nombre, $idPvInt);
        if ($productos->isEmpty()) {
            return response()->json([
                'message' => 'No hay productos para el punto de venta y filtros indicados.',
                'status' => 404,
            ], 404);
        }

        $sufijoFechas = preg_replace('/[^0-9\-]/', '', $fechaInicio)
            .'_'.preg_replace('/[^0-9\-]/', '', $fechaFin);
        $maxDetalleExcel = (int) config('contabilidad.inventario_valorizado_excel_max_productos_detalle', 25);
        $kardexCompletoExcel = $productos->count() === 1 || $productos->count() <= $maxDetalleExcel;

        if ($productos->count() === 1) {
            $producto = $productos->first();
            $codigoArchivo = trim((string) ($producto->codigoBarra ?? ''));
            $nombreArchivo = 'INVENTARIO_VALORIZADO_SUNAT_'
                .preg_replace('/[^A-Za-z0-9_\-]/', '_', $codigoArchivo !== '' ? $codigoArchivo : 'producto')
                .'_'.$sufijoFechas.'.xlsx';
        } elseif ($kardexCompletoExcel) {
            $nombreArchivo = 'INVENTARIO_VALORIZADO_SUNAT_TODOS_'.$sufijoFechas.'.xlsx';
        } else {
            $nombreArchivo = 'INVENTARIO_VALORIZADO_SUNAT_RESUMEN_'.$sufijoFechas.'.xlsx';
        }

        $tmpPath = null;
        try {

            $reportes = $inventario->buildReportes(
                $productos,
                $idPvInt,
                $fechaInicio,
                $fechaFin,
                $incluirSaldoInicial,
                true,
                $kardexCompletoExcel
            );

            if (! $kardexCompletoExcel) {
                $spreadsheet = $inventario->buildResumenSpreadsheet($reportes, $fechaInicio, $fechaFin);
            } elseif (count($reportes) === 1) {
                $reporte = $reportes[0];
                $celdasPorFila = array_map(
                    static fn (array $f) => $f['celdas'] ?? [],
                    $reporte['filas']
                );
                $spreadsheet = $inventario->fillTemplateSpreadsheet(
                    $celdasPorFila,
                    $fechaInicio,
                    $fechaFin,
                    trim((string) ($reporte['producto']['codigoBarra'] ?? '')),
                    trim((string) ($reporte['producto']['nombre'] ?? ''))
                );
            } else {
                $bloques = [];
                foreach ($reportes as $reporte) {
                    $bloques[] = [
                        'celdasPorFila' => array_map(
                            static fn (array $f) => $f['celdas'] ?? [],
                            $reporte['filas']
                        ),
                        'codigoBarra' => trim((string) ($reporte['producto']['codigoBarra'] ?? '')),
                        'nombreProducto' => trim((string) ($reporte['producto']['nombre'] ?? '')),
                    ];
                }
                $spreadsheet = $inventario->fillTemplateSpreadsheetMulti($bloques, $fechaInicio, $fechaFin);
            }
            $spreadsheet->garbageCollect();

            $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inv_valorizado_'.uniqid('', true).'.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save($tmpPath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
        } catch (\RuntimeException $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
            $msg = $e->getMessage();
            $status = (stripos($msg, 'zip') !== false || stripos($msg, 'ZipArchive') !== false) ? 503 : 422;

            return response()->json([
                'message' => $msg,
                'status' => $status,
            ], $status);
        } catch (\Throwable $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }

            $payload = [
                'message' => 'Error al generar el Excel de inventario valorizado SUNAT.',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
            if (config('app.debug')) {
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }

            return response()->json($payload, 500);
        }

        return response()->download($tmpPath, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Listado JSON – KARDEX GENERAL (CABECERA paginada + DETALLE si hay producto seleccionado).
     *
     * Query: fechaInicio, fechaFin, idPuntoVenta, codigo, nombre (filtros parciales).
     * idProducto o codigoBarra (exacto): devuelve además el DETALLE de ese producto.
     * page, per_page: paginación solo de CABECERA.
     */
    public function kardexGeneralListado(Request $request, KardexGeneralService $kardex): JsonResponse
    {
        $auth = $this->autenticarJwtContabilidad();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $validacion = $this->validarParamsKardexGeneral($request);
        if ($validacion instanceof JsonResponse) {
            return $validacion;
        }

        [
            $fechaInicio,
            $fechaFin,
            $idPvInt,
            $idProducto,
            $codigoBarra,
            $codigo,
            $nombre,
            $incluirSaldoInicial,
        ] = $validacion;

        $kardex->extendExecutionTime();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = $kardex->normalizarPerPage(
            ($request->query('per_page') !== null && $request->query('per_page') !== '')
                ? (int) $request->query('per_page')
                : null
        );

        $paginado = $kardex->resolveProductosPaginados(
            $idProducto,
            $codigoBarra,
            $codigo,
            $nombre,
            $idPvInt,
            $page,
            $perPage
        );
        $paginator = $paginado['paginator'];
        $productosPagina = $paginado['productos'];

        if ($paginator->total() === 0) {
            return response()->json([
                'message' => 'No hay productos para el punto de venta y filtros indicados.',
                'status' => 404,
            ], 404);
        }

        try {
            $cabecera = $kardex->buildFilasCabecera(
                $productosPagina,
                $idPvInt,
                $fechaInicio,
                $fechaFin,
                $incluirSaldoInicial
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo armar el KARDEX GENERAL.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }

        $detalle = [];
        $productoSeleccionado = null;
        if (($idProducto !== null && $idProducto > 0) || ($codigoBarra !== null && $codigoBarra !== '')) {
            $productoDetalle = $kardex->resolveProductos($idProducto, $codigoBarra, null, null, $idPvInt)->first();
            if ($productoDetalle) {
                $productoSeleccionado = [
                    'id' => (int) $productoDetalle->id,
                    'codigoBarra' => (string) ($productoDetalle->codigoBarra ?? ''),
                    'nombre' => (string) ($productoDetalle->nombre ?? ''),
                    'categoria' => (string) ($productoDetalle->nombreCategoria ?? ''),
                    'um' => (string) ($productoDetalle->nombreUm ?? ''),
                    'idPuntoVenta' => (int) $productoDetalle->idPuntoVenta,
                ];
                try {
                    $detalle = $kardex->buildFilasDetalle(
                        $productoDetalle,
                        $idPvInt,
                        $fechaInicio,
                        $fechaFin,
                        $incluirSaldoInicial
                    );
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => 'No se pudo armar el detalle del KARDEX GENERAL.',
                        'error' => $e->getMessage(),
                        'status' => 500,
                    ], 500);
                }
            }
        }

        $paginacion = $kardex->metaPaginacion($paginator, count($cabecera));

        return response()->json([
            'reporte' => 'KARDEX GENERAL',
            'cabeceras_cabecera' => KardexGeneralService::CABECERAS_CABECERA,
            'cabeceras_detalle' => KardexGeneralService::CABECERAS_DETALLE,
            'periodo' => [
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin,
                'idPuntoVenta' => $idPvInt,
                'incluir_saldo_inicial' => $incluirSaldoInicial,
            ],
            'filtros' => [
                'codigo' => $codigo ?? '',
                'nombre' => $nombre ?? '',
                'idProducto' => $idProducto,
                'codigoBarra' => $codigoBarra ?? '',
            ],
            'paginacion' => $paginacion,
            'producto_seleccionado' => $productoSeleccionado,
            'cabecera' => $cabecera,
            'detalle' => $detalle,
            'total_detalle_registros' => count($detalle),
            'status' => 200,
        ], 200);
    }

    /**
     * Excel KARDEX GENERAL (todas las hojas CABECERA + DETALLE; ignora page/per_page).
     */
    public function kardexGeneralExcel(Request $request, KardexGeneralService $kardex): BinaryFileResponse|JsonResponse
    {
        $auth = $this->autenticarJwtContabilidad();
        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        if (! class_exists(IOFactory::class)) {
            return response()->json([
                'message' => 'Falta la librería PhpSpreadsheet.',
                'status' => 503,
            ], 503);
        }

        if (! extension_loaded('zip') || ! class_exists(\ZipArchive::class)) {
            return response()->json([
                'message' => 'PHP requiere la extensión zip (ZipArchive) para archivos .xlsx.',
                'status' => 503,
            ], 503);
        }

        $validacion = $this->validarParamsKardexGeneral($request);
        if ($validacion instanceof JsonResponse) {
            return $validacion;
        }

        [
            $fechaInicio,
            $fechaFin,
            $idPvInt,
            $idProducto,
            $codigoBarra,
            $codigo,
            $nombre,
            $incluirSaldoInicial,
        ] = $validacion;

        $kardex->extendExecutionTime();

        $productos = $kardex->resolveProductos($idProducto, $codigoBarra, $codigo, $nombre, $idPvInt);
        if ($productos->isEmpty()) {
            return response()->json([
                'message' => 'No hay productos para el punto de venta y filtros indicados.',
                'status' => 404,
            ], 404);
        }

        $sufijoFechas = preg_replace('/[^0-9\-]/', '', $fechaInicio)
            .'_'.preg_replace('/[^0-9\-]/', '', $fechaFin);
        $nombreArchivo = 'KARDEX_GENERAL_'.$sufijoFechas.'.xlsx';

        $tmpPath = null;
        try {
            $filasCabecera = $kardex->buildFilasCabecera(
                $productos,
                $idPvInt,
                $fechaInicio,
                $fechaFin,
                $incluirSaldoInicial
            );
            $filasDetalle = $kardex->buildFilasDetalleTodos(
                $productos,
                $idPvInt,
                $fechaInicio,
                $fechaFin,
                $incluirSaldoInicial
            );

            $spreadsheet = $kardex->fillTemplateSpreadsheet(
                $filasCabecera,
                $filasDetalle,
                $fechaInicio,
                $fechaFin,
                (string) ($codigo ?? ''),
                (string) ($nombre ?? '')
            );
            $spreadsheet->garbageCollect();

            $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kardex_general_'.uniqid('', true).'.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save($tmpPath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);
        } catch (\RuntimeException $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
            $msg = $e->getMessage();
            $status = (stripos($msg, 'zip') !== false || stripos($msg, 'ZipArchive') !== false) ? 503 : 422;

            return response()->json([
                'message' => $msg,
                'status' => $status,
            ], $status);
        } catch (\Throwable $e) {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }

            $payload = [
                'message' => 'Error al generar el Excel KARDEX GENERAL.',
                'error' => $e->getMessage(),
                'status' => 500,
            ];
            if (config('app.debug')) {
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }

            return response()->json($payload, 500);
        }

        return response()->download($tmpPath, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return JsonResponse|array{0: string, 1: string, 2: int, 3: ?int, 4: ?string, 5: ?string, 6: ?string, 7: bool}
     */
    private function validarParamsKardexGeneral(Request $request): JsonResponse|array
    {
        return $this->validarParamsInventarioValorizado($request);
    }

    /**
     * @return JsonResponse|array{0: string, 1: string, 2: int, 3: ?int, 4: ?string, 5: ?string, 6: ?string, 7: bool}
     */
    private function validarParamsInventarioValorizado(Request $request): JsonResponse|array
    {
        $fechaInicio = (string) $request->query('fechaInicio', '');
        $fechaFin = (string) $request->query('fechaFin', '');
        if ($fechaInicio === '' || $fechaFin === '') {
            return response()->json([
                'message' => 'Parámetros fechaInicio y fechaFin son obligatorios (formato Y-m-d).',
                'status' => 422,
            ], 422);
        }

        $idPv = $request->query('idPuntoVenta');
        if ($idPv === null || $idPv === '') {
            return response()->json([
                'message' => 'Parámetro idPuntoVenta es obligatorio.',
                'status' => 422,
            ], 422);
        }
        $idPvInt = (int) $idPv;
        if ($idPvInt <= 0) {
            return response()->json([
                'message' => 'idPuntoVenta inválido.',
                'status' => 422,
            ], 422);
        }

        $idProductoRaw = $request->query('idProducto');
        $idProducto = ($idProductoRaw !== null && $idProductoRaw !== '') ? (int) $idProductoRaw : null;
        $codigoBarra = trim((string) $request->query('codigoBarra', ''));
        $codigo = trim((string) $request->query('codigo', ''));
        $nombre = trim((string) $request->query('nombre', ''));

        $incluirSaldoInicial = filter_var(
            $request->query('incluirSaldoInicial', '1'),
            FILTER_VALIDATE_BOOLEAN
        );

        return [
            $fechaInicio,
            $fechaFin,
            $idPvInt,
            ($idProducto !== null && $idProducto > 0) ? $idProducto : null,
            $codigoBarra !== '' ? $codigoBarra : null,
            $codigo !== '' ? $codigo : null,
            $nombre !== '' ? $nombre : null,
            $incluirSaldoInicial,
        ];
    }

    private function autenticarJwtContabilidad(): JsonResponse|true
    {
        try {
            if (! JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        return true;
    }
}
