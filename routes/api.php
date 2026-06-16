<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/register/', 'App\Http\Controllers\UserController@register');
Route::post('/login/', 'App\Http\Controllers\UserController@authenticate');

// SUBIR ARCHIVOS
Route::post('/upload/{tipo}/{id}', 'App\Http\Controllers\UploadController@upload');

// FRONT END
Route::get('/submenu/frontend/{id}', 'App\Http\Controllers\SubMenuController@obtenerFrontend');

Route::get('/imprimir/{id}', 'App\Http\Controllers\ProductosController@imprimir');

// Rutas publicas
Route::get('productos-activos', 'App\Http\Controllers\ProductosController@productosActivos');
Route::get('categorias-activas', 'App\Http\Controllers\CategoriasController@categoriasActivas');

// Ruta pública para búsqueda de productos activos
Route::get('productos-buscar', [App\Http\Controllers\ProductosController::class, 'productosBuscar']);

Route::get('datos-empresa', 'App\Http\Controllers\DatosEmpresaController@datosEmpresaPublicos');
Route::get('puntoVenta-activos', 'App\Http\Controllers\PuntoVentaController@puntoVentaActivos');

Route::post('/productos/upload-imagen', 'App\\Http\\Controllers\\ProductosController@uploadImagen');
Route::post('/productos/delete-imagen', 'App\\Http\\Controllers\\ProductosController@deleteImagen');

Route::post('/categorias/upload-imagen', 'App\\Http\\Controllers\\CategoriasController@uploadImagen');
Route::post('/categorias/delete-imagen', 'App\\Http\\Controllers\\CategoriasController@deleteImagen');

Route::get('/reporteRecibos/{id}', 'App\Http\Controllers\RecibosController@reporteRecibos');
Route::get('/reportePedido/{id}', 'App\Http\Controllers\PedidosController@reportePedido');
Route::get('/reporteOrdenRequerimiento/{id}', 'App\Http\Controllers\OrdenRequerimientoController@reporteOrdenRequerimiento');
Route::get('/reporteAbastecimiento/{id}', 'App\Http\Controllers\AbastecimientosController@reporteAbastecimiento');
Route::get('/reporteCotizacion/{id}', 'App\Http\Controllers\CotizacionController@reporteCotizacion');
Route::get('/reporteCierreCajaTodos/{id}/{fecha}', 'App\Http\Controllers\CierreCajaController@reporteCierreCajaTodos');
Route::get('/reporteCierreCaja/{id}/{fecha}/{idUusario}', 'App\Http\Controllers\CierreCajaController@reporteCierreCaja');
Route::get('/listaIngresoSobrante/{id}', 'App\Http\Controllers\CierreCajaController@listaIngresoSobrante');

// VALORIZADO
Route::get('/valorizado', 'App\Http\Controllers\ValorizadoController@store');

// Creamos un group para indicar cuales son las rutas que necesitan autenticación:
Route::group(['middleware' => ['jwt.verify']], function () {
    Route::post('logeado', 'App\Http\Controllers\UserController@getAuthenticatedUser');
    Route::put('/user/perfil/{id}', 'App\Http\Controllers\UserController@perfil');
    Route::put('/user/role/{id}', 'App\Http\Controllers\UserController@roleAdmin');
    Route::post('/user/cambiarPassword/{id}', 'App\Http\Controllers\UserController@cambiarPassword');
    Route::get('/user/cargarRoles', 'App\Http\Controllers\UserController@cargarRoles');
    Route::get('/user/permisos/{idUsuario}', 'App\Http\Controllers\UserController@cargarPermisos');
    Route::get('/userPermisos', 'App\Http\Controllers\UserController@cargarUserPermisos');
    Route::get('/user/cargarUsuarios', 'App\Http\Controllers\UserController@cargarUsuarios');

    // CAMBIAR ESTADOS
    Route::put('/menu/estado/{id}', 'App\Http\Controllers\MenuController@cambiarEstado');
    Route::put('/user/estado/{id}', 'App\Http\Controllers\UserController@cambiarEstado');
    Route::put('/submenu/estado/{id}', 'App\Http\Controllers\SubMenuController@cambiarEstado');

    // CAMBIAR ORDEN
    Route::put('/menu/orden/{id}', 'App\Http\Controllers\MenuController@cambiarOrden');
    Route::put('/modulo/orden/{id}', 'App\Http\Controllers\ModuloController@cambiarOrden');
    Route::put('/submenu/orden/{id}', 'App\Http\Controllers\SubMenuController@cambiarOrden');
    Route::get('/submenu/obtener/{id}', 'App\Http\Controllers\SubMenuController@obtener');
    Route::get('/recibosMedioPagoDia/{idPuntoVenta}/{idUsuario}/{dia}', 'App\Http\Controllers\RecibosMedioPagoController@recibosMedioPagoDia');

    Route::get('/seriesTickets/{id}', 'App\Http\Controllers\SeriesTicketsController@cargar');
    Route::get('/clientes/buscarClientes/{documento}/{idPuntoVenta}', 'App\Http\Controllers\ClientesController@buscarClientes');
    Route::get('/bancos/buscarClientes/{documento}', 'App\Http\Controllers\BancosController@buscarClientes');
    Route::post('/recibos/enviarCorreo', 'App\Http\Controllers\RecibosController@enviarCorreo');
    Route::put('/cotizacion/cambiarEstado/{id}', 'App\Http\Controllers\CotizacionController@cambiarEstado');
    Route::get('/productos/codigoBarras/{id}/{codigo}', 'App\Http\Controllers\ProductosController@codigoBarras');
    Route::get('/productos/buscar/{id}/{texto}', 'App\Http\Controllers\ProductosController@buscarProductos');
    Route::get('/recibos/devoluciones/{id}', 'App\Http\Controllers\RecibosController@index');
    Route::post('/cierreCajasGeneral', 'App\Http\Controllers\CierreCajaController@guardarGeneral');
    Route::get('/abastecimiento/{id}', 'App\Http\Controllers\PuntoVentaController@abastecimiento');
    Route::get('/reportes/inventario/{id}', 'App\Http\Controllers\ReportesController@inventario');
    Route::get('/reportes/movimientos/{id}', 'App\Http\Controllers\ReportesController@movimientos');
    Route::get('/reportes/ventasTotales/{id}', 'App\Http\Controllers\ReportesController@ventasTotales');
    Route::post('/actualizacionInventariosBuscarProductos', 'App\Http\Controllers\ActualizacionInventariosController@buscarProductos');
    Route::get('/cargarProductosVentas/{id}', 'App\Http\Controllers\ProductosController@cargarProductosVentas');

    Route::post('/ventasTotales/ventasMes', 'App\Http\Controllers\VentasTotalesController@ventasMes');
    Route::post('/ventasTotales/ventasAnio', 'App\Http\Controllers\VentasTotalesController@ventasAnio');
    Route::post('/ventasTotalesNew/ventasMes', 'App\Http\Controllers\VentasTotalesController@ventasMeNews');
    Route::post('/ventasTotalesNew/ventasAnio', 'App\Http\Controllers\VentasTotalesController@ventasAnioNew');

    Route::post('/buscarPorFecha', 'App\Http\Controllers\RecibosController@buscarPorFecha');
    Route::get('/recibos/numeracion', 'App\Http\Controllers\RecibosController@numeracion');
    Route::post('/buscarPorFechaCompras', 'App\Http\Controllers\ComprasController@buscarPorFecha');

    // Contabilidad – RCE SIRE compras (SUNAT)
    Route::get('/contabilidad/rce-compras', 'App\Http\Controllers\ContabilidadController@rceComprasListado');
    Route::get('/contabilidad/rce-compras/excel', 'App\Http\Controllers\ContabilidadController@rceComprasExcel');
    Route::get('/contabilidad/rvie-ventas', 'App\Http\Controllers\ContabilidadController@rvieVentasListado');
    Route::get('/contabilidad/rvie-ventas/excel', 'App\Http\Controllers\ContabilidadController@rvieVentasExcel');
    Route::get('/contabilidad/inventario-valorizado', 'App\Http\Controllers\ContabilidadController@inventarioValorizadoListado');
    Route::get('/contabilidad/inventario-valorizado/excel', 'App\Http\Controllers\ContabilidadController@inventarioValorizadoExcel');
    Route::get('/contabilidad/kardex-general', 'App\Http\Controllers\ContabilidadController@kardexGeneralListado');
    Route::get('/contabilidad/kardex-general/excel', 'App\Http\Controllers\ContabilidadController@kardexGeneralExcel');
    Route::post('/buscarPorFechaPedidos', 'App\Http\Controllers\PedidosController@buscarPorFecha');
    Route::post('/buscarPorFechaOrdenRequerimiento', 'App\Http\Controllers\OrdenRequerimientoController@buscarPorFecha');
    Route::post('/buscarPorFechaActualizacionInventarios', 'App\Http\Controllers\ActualizacionInventariosController@buscarPorFecha');

    Route::post('/guardarReporteVentas', 'App\Http\Controllers\CierreCajaController@guardarReporteVentas');
    Route::get('/reportes/comparacionVentasVendedores/{id}', 'App\Http\Controllers\ReportesController@comparacionVentasVendedores');

    // comprobantes helpers used by angular
    Route::get('/comprobantes/series', 'App\Http\Controllers\ComprobantesController@series');
    Route::get('/comprobantes/numeracion', 'App\Http\Controllers\ComprobantesController@numeracion');
    Route::get('/comprobantes/tipos-documento', 'App\Http\Controllers\ComprobantesController@tiposDocumento');

    // cliente lookup by ruc
    Route::get('/clientes/buscar', 'App\Http\Controllers\ClientesController@buscar');
    Route::get('/reportes/reporteProductosPuntoVenta/{id}', 'App\Http\Controllers\ReportesController@reporteProductosPuntoVenta');
    Route::get('/numeroEnvio/{id}', 'App\Http\Controllers\AbastecimientosController@numeroEnvio');
    Route::post('/gananciaTiendas', 'App\Http\Controllers\ReportesController@gananciaTiendas');
    Route::post('/reportes/ventasCategoriaDetallado', 'App\Http\Controllers\ReportesController@ventasCategoriaDetallado');
    Route::get('/reportes/valorizacionProductosTienda/{id}', 'App\Http\Controllers\ReportesController@valorizacionProductosTienda');
    Route::get('/reportes/flujoInversion/{id}', 'App\Http\Controllers\ReportesController@flujoInversion');
    Route::get('/reporteMensualporVendedorComponent/{id}', 'App\Http\Controllers\VentasTotalesController@reporteMensualporVendedorComponent');
    Route::post('/comprasResumidas', 'App\Http\Controllers\ReportesController@comprasResumidas');
    Route::post('/ventasComprasDiarias', 'App\Http\Controllers\ReportesController@ventasComprasDiarias');

    // comprobantes endpoints for angular service
    Route::get('/comprobantes', 'App\Http\Controllers\ComprobantesController@index');
    Route::get('/comprobantes/tipos', 'App\Http\Controllers\ComprobantesController@tipos');
    Route::post('/comprobantes', 'App\Http\Controllers\ComprobantesController@store');
    Route::post('/comprobantes/efact', 'App\Http\Controllers\ComprobantesController@integrarEfact');
    // OSE eFact: obtener CDR, XML firmado y PDF por id de comprobante
    Route::get('/comprobantes/{id}/cdr', 'App\Http\Controllers\ComprobantesController@cdr');
    Route::get('/comprobantes/{id}/xml', 'App\Http\Controllers\ComprobantesController@xml');
    Route::get('/comprobantes/{id}/pdf', 'App\Http\Controllers\ComprobantesController@pdf');

    // OSE eFact: listado unificado recibos/comprobantes, descarga por ticket y emisión en lote
    Route::get('/efact/emisiones', 'App\Http\Controllers\EfactController@emisiones');
    Route::get('/efact/cdr', 'App\Http\Controllers\EfactController@cdrPorQuery');
    Route::get('/efact/xml', 'App\Http\Controllers\EfactController@xmlPorQuery');
    Route::get('/efact/pdf', 'App\Http\Controllers\EfactController@pdfPorQuery');
    Route::get('/efact/cdr/{ticket}', 'App\Http\Controllers\EfactController@cdrPorTicket')->where('ticket', '[^/]+');
    Route::get('/efact/xml/{ticket}', 'App\Http\Controllers\EfactController@xmlPorTicket')->where('ticket', '[^/]+');
    Route::get('/efact/pdf/{ticket}', 'App\Http\Controllers\EfactController@pdfPorTicket')->where('ticket', '[^/]+');
    Route::post('/efact/emision-lote', 'App\Http\Controllers\EfactController@emisionLote');
    Route::post('/efact/sincronizar-estados', 'App\Http\Controllers\EfactController@sincronizarEstados');
    Route::post('/ventasComprasDiarias', 'App\Http\Controllers\ReportesController@ventasComprasDiarias');
    Route::get('/obtenerProductosFaltantesEditar/{id}', 'App\Http\Controllers\ProductosFaltantesController@obtenerProductosFaltantesEditar');
    Route::post('/sobranteVsFaltantes', 'App\Http\Controllers\ReportesController@sobranteVsFaltantes');
    Route::get('/proveedoresProductos/{id}', 'App\Http\Controllers\ReportesController@proveedoresProductos');
    Route::get('/reportes/productosStockMinimo/{id}', 'App\Http\Controllers\ReportesController@productosStockMinimo');

    Route::get('/productosProveedores/{id}/{idProducto}', 'App\Http\Controllers\ProductosProveedorController@cargar');
    
    // API para obtener detalles de un abastecimiento específico
    Route::get('/abastecimientos/detalles/{id}', 'App\Http\Controllers\AbastecimientosController@detalles');

    // FRONTEND
    Route::resource('/menu', 'App\Http\Controllers\MenuController');
    Route::resource('/submenu', 'App\Http\Controllers\SubMenuController');
    Route::resource('/user', 'App\Http\Controllers\UserController');
    Route::resource('/modulo', 'App\Http\Controllers\ModuloController');
    Route::resource('/moduloSub', 'App\Http\Controllers\ModuloSubController');
    Route::resource('/permisos', 'App\Http\Controllers\RolesModuloOperacionController');
    Route::resource('/puntosVenta', 'App\Http\Controllers\PuntoVentaController');
    Route::resource('/seriesTickets', 'App\Http\Controllers\SeriesTicketsController');
    Route::resource('/numeracionTickets', 'App\Http\Controllers\NumeracionTicketsController');
    Route::resource('/cajas', 'App\Http\Controllers\CajasController');
    Route::resource('/proveedores', 'App\Http\Controllers\ProveedoresController');
    Route::resource('/clientes', 'App\Http\Controllers\ClientesController');
    Route::resource('/bancos', 'App\Http\Controllers\BancosController');
    Route::resource('/depositos', 'App\Http\Controllers\DepositosController');
    Route::resource('/monedas', 'App\Http\Controllers\MonedasController');
    Route::resource('/pagosRealizar', 'App\Http\Controllers\PagosRealizarController');
    Route::resource('/pagosDetalles', 'App\Http\Controllers\PagosRealizarDetallesController');
    Route::resource('/tiposPago', 'App\Http\Controllers\TiposPagoController');
    Route::resource('/tipoCambio', 'App\Http\Controllers\TiposCambioController');
    Route::resource('/ubigeo', 'App\Http\Controllers\UbigeoController');
    Route::resource('/puntoVentasUser', 'App\Http\Controllers\PuntoventauserController');
    Route::resource('/tipoDoi', 'App\Http\Controllers\TipoDoiController');
    Route::resource('/items', 'App\Http\Controllers\ItemsController');
    Route::resource('/categorias', 'App\Http\Controllers\CategoriasController');
    Route::resource('/unidadMedidas', 'App\Http\Controllers\UnidadMedidaController');
    Route::resource('/productos', 'App\Http\Controllers\ProductosController');
    Route::resource('/productoAjustes', 'App\Http\Controllers\ProductoAjustesController');
    Route::resource('/almacenes', 'App\Http\Controllers\AlmacenController');
    Route::resource('/ubicaciones', 'App\Http\Controllers\UbicacionesController');
    Route::resource('/tipoDocumento', 'App\Http\Controllers\TipoDocumentoController');
    Route::resource('/compras', 'App\Http\Controllers\ComprasController');
    Route::resource('/comprasdetalles', 'App\Http\Controllers\ComprasDetallesController');
    Route::resource('/roles', 'App\Http\Controllers\RolesController');
    Route::resource('/medioPago', 'App\Http\Controllers\MedioPagoController');
    Route::resource('/recibosMonedas', 'App\Http\Controllers\RecibosMonedasController');
    Route::resource('/recibosMedioPago', 'App\Http\Controllers\RecibosMedioPagoController');
    Route::resource('/recibos', 'App\Http\Controllers\RecibosController');
    Route::resource('/recibosDetalles', 'App\Http\Controllers\RecibosDetallesController');
    Route::resource('/cotizacion', 'App\Http\Controllers\CotizacionController');
    Route::resource('/cotizacionDetalles', 'App\Http\Controllers\CotizacionDetallesController');
    Route::resource('/devoluciones', 'App\Http\Controllers\DevolucionesController');
    Route::resource('/cierreCajas', 'App\Http\Controllers\CierreCajaController');
    Route::resource('/abastecimientos', 'App\Http\Controllers\AbastecimientosController');
    Route::resource('/compraAjustes', 'App\Http\Controllers\ComprasAjustesController');
    Route::resource('/ventasTotales', 'App\Http\Controllers\VentasTotalesController');
    Route::resource('/datosEmpresa', 'App\Http\Controllers\DatosEmpresaController');
    Route::resource('/pedidos', 'App\Http\Controllers\PedidosController');
    Route::resource('/valorizadoCrud', 'App\Http\Controllers\ValorizadoController');
    Route::resource('/productosFaltantes', 'App\Http\Controllers\ProductosFaltantesController');
    Route::resource('/actualizacionInventarios', 'App\Http\Controllers\ActualizacionInventariosController');
    Route::resource('/productosProveedores', 'App\Http\Controllers\ProductosProveedorController');
    Route::resource('/ordenRequerimiento', 'App\Http\Controllers\OrdenRequerimientoController');
    Route::resource('/ajustesInventario', 'App\Http\Controllers\AjusteInventarioController');
});
