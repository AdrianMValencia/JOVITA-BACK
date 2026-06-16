<?php

namespace App\Http\Controllers;

use App\Models\Compras;
use App\Models\Productos;
use App\Models\ProductosProveedor;
use App\Models\VentasTotales;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReportesController extends Controller
{
    public function inventario($id)
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

        if ($id == '10') {
            $inventario = Productos::selectRaw('tbl_productos.codigoBarra, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_compras_detalle.precio costo, tbl_productos.precio, stockActual existencia')
            ->leftJoin('tbl_compras_detalle', 'tbl_productos.id', '=', 'tbl_compras_detalle.idProducto')
            ->leftJoin('tbl_compras', 'tbl_compras_detalle.idCompra', '=', 'tbl_compras.id')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->whereIn('tbl_compras.idPuntoVenta', [6, 7, 8, 11])
            ->get();

            $compras = Compras::whereIn('idPuntoVenta', [6, 7, 8, 11])->get()->load('detalles');
        } else {
            $inventario = Productos::selectRaw('tbl_productos.codigoBarra, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_compras_detalle.precio costo, tbl_productos.precio, stockActual existencia')
            ->leftJoin('tbl_compras_detalle', 'tbl_productos.id', '=', 'tbl_compras_detalle.idProducto')
            ->leftJoin('tbl_compras', 'tbl_compras_detalle.idCompra', '=', 'tbl_compras.id')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->where('tbl_compras.idPuntoVenta', $id)
            ->get();

            $compras = Compras::where('idPuntoVenta', $id)->get()->load('detalles');
        }

        $costoInventario = 0;
        $cantidadProductosInventario = 0;
        foreach ($compras as $value) {
            foreach ($value->detalles as $value2) {
                $costoInventario = $costoInventario + $value2->precio;
                $cantidadProductosInventario = $cantidadProductosInventario + $value2->cantidad;
            }
        }

        $data = [
            'inventario' => $inventario,
            'costoInventario' => $costoInventario,
            'cantidadProductosInventario' => $cantidadProductosInventario,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function movimientos($id)
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

        if ($id == '10') {
            $recibos = Productos::selectRaw("DATE_FORMAT(tbl_recibos.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Salida' tipo, tbl_recibo_detalles.cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_recibos.fechaEmision, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_recibo_detalles', 'tbl_productos.id', '=', 'tbl_recibo_detalles.idProducto')
            ->leftJoin('tbl_recibos', 'tbl_recibo_detalles.idRecibo', '=', 'tbl_recibos.id')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->whereIn('tbl_recibos.idPuntoVenta', [6, 7, 8, 11])
            ->get();

            $compras = Productos::selectRaw("DATE_FORMAT(tbl_compras.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Entrada' tipo, tbl_compras_detalle.cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_compras.fechaCompra, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_compras_detalle', 'tbl_productos.id', '=', 'tbl_compras_detalle.idProducto')
            ->leftJoin('tbl_compras', 'tbl_compras_detalle.idCompra', '=', 'tbl_compras.id')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->whereIn('tbl_compras.idPuntoVenta', [6, 7, 8, 11])
            ->get();

            $devoluciones = Productos::selectRaw("DATE_FORMAT(tbl_devoluciones.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Devoluciones' tipo, tbl_devoluciones.cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_devoluciones.created_at, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_devoluciones', 'tbl_productos.id', '=', 'tbl_devoluciones.idProducto')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->where('tbl_devoluciones.stockActual', true)
            ->whereIn('tbl_devoluciones.idPuntoVenta', [6, 7, 8, 11])
            ->get();

            $ajustes = Productos::selectRaw("DATE_FORMAT(tbl_producto_ajustes.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Ajustes' tipo, tbl_producto_ajustes.cantidadAjuste cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_producto_ajustes.created_at, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_producto_ajustes', 'tbl_productos.id', '=', 'tbl_producto_ajustes.idProducto')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->whereIn('tbl_producto_ajustes.idPuntoVenta', [6, 7, 8, 11])
            ->get();
        } else {
            $recibos = Productos::selectRaw("DATE_FORMAT(tbl_recibos.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Salida' tipo, tbl_recibo_detalles.cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_recibos.fechaEmision, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_recibo_detalles', 'tbl_productos.id', '=', 'tbl_recibo_detalles.idProducto')
            ->leftJoin('tbl_recibos', 'tbl_recibo_detalles.idRecibo', '=', 'tbl_recibos.id')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->where('tbl_recibos.idPuntoVenta', $id)
            ->get();

            $compras = Productos::selectRaw("DATE_FORMAT(tbl_compras.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Entrada' tipo, tbl_compras_detalle.cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_compras.fechaCompra, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_compras_detalle', 'tbl_productos.id', '=', 'tbl_compras_detalle.idProducto')
            ->leftJoin('tbl_compras', 'tbl_compras_detalle.idCompra', '=', 'tbl_compras.id')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->where('tbl_compras.idPuntoVenta', $id)
            ->get();

            $devoluciones = Productos::selectRaw("DATE_FORMAT(tbl_devoluciones.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Devoluciones' tipo, tbl_devoluciones.cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_devoluciones.created_at, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_devoluciones', 'tbl_productos.id', '=', 'tbl_devoluciones.idProducto')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->where([['tbl_devoluciones.idPuntoVenta', $id], ['tbl_devoluciones.stockActual', true]])
            ->get();

            $ajustes = Productos::selectRaw("DATE_FORMAT(tbl_producto_ajustes.created_at, '%h:%i %p') hora, tbl_productos.id idProducto, tbl_productos.nombre producto, tbl_productos.stockActual habia, 'Ajustes' tipo, tbl_producto_ajustes.cantidadAjuste cantidad, tbl_categorias.id idCategoria, tbl_categorias.nombre categoria,  DATE_FORMAT(tbl_producto_ajustes.created_at, '%Y-%m-%d') fecha")
            ->leftJoin('tbl_producto_ajustes', 'tbl_productos.id', '=', 'tbl_producto_ajustes.idProducto')
            ->leftJoin('tbl_categorias', 'tbl_productos.idCategoria', '=', 'tbl_categorias.id')
            ->where('tbl_producto_ajustes.idPuntoVenta', $id)
            ->get();
        }

        $data = [
            'recibos' => $recibos,
            'compras' => $compras,
            'devoluciones' => $devoluciones,
            'ajustes' => $ajustes,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function ventasTotales($id)
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

        if ($id == '10') {
            $ventasTotales = Productos::selectRaw("DATE_FORMAT(tbl_recibos.fechaEmision, '%Y-%m-%d') fecha, IFNULL(SUM(tbl_recibo_detalles.cantidad*tbl_recibo_detalles.precio), 0.00) venta, IFNULL(SUM(tbl_recibo_detalles.cantidad*tbl_productos.precioCompra), 0.00) compra, IFNULL((SUM(tbl_recibo_detalles.cantidad*tbl_recibo_detalles.precio) - SUM(tbl_recibo_detalles.cantidad*tbl_productos.precioCompra)), 0.00) ganancia")
            ->leftJoin('tbl_recibo_detalles', 'tbl_productos.id', '=', 'tbl_recibo_detalles.idProducto')
            ->leftJoin('tbl_recibos', 'tbl_recibo_detalles.idRecibo', '=', 'tbl_recibos.id')
            ->whereIn('tbl_recibos.idPuntoVenta', [6, 7, 8, 11])
            ->groupByRaw("DATE_FORMAT(tbl_recibos.fechaEmision, '%Y-%m-%d')")
            ->get();
        } else {
            $ventasTotales = Productos::selectRaw("DATE_FORMAT(tbl_recibos.fechaEmision, '%Y-%m-%d') fecha, IFNULL(SUM(tbl_recibo_detalles.cantidad*tbl_recibo_detalles.precio), 0.00) venta, IFNULL(SUM(tbl_recibo_detalles.cantidad*tbl_productos.precioCompra), 0.00) compra, IFNULL((SUM(tbl_recibo_detalles.cantidad*tbl_recibo_detalles.precio) - SUM(tbl_recibo_detalles.cantidad*tbl_productos.precioCompra)), 0.00) ganancia")
            ->leftJoin('tbl_recibo_detalles', 'tbl_productos.id', '=', 'tbl_recibo_detalles.idProducto')
            ->leftJoin('tbl_recibos', 'tbl_recibo_detalles.idRecibo', '=', 'tbl_recibos.id')
            ->where('tbl_recibos.idPuntoVenta', $id)
            ->groupByRaw("DATE_FORMAT(tbl_recibos.fechaEmision, '%Y-%m-%d')")
            ->get();
        }

        $date = Carbon::now();
        $date = $date->format('H');

        if ($date > 23) {
            foreach ($ventasTotales as $value) {
                $ventas = new VentasTotales();
                $ventas->fecha = $value->fecha;
                $ventas->venta = $value->venta;
                $ventas->compra = $value->compra;
                $ventas->ganancia = $value->ganancia;
                $ventas->idPuntoVenta = $id;
                $ventas->save();
            }
        }

        $data = [
            'ventasTotales' => $ventasTotales,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function comparacionVentasVendedores($id)
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

        if ($id == 10) {
            // where date(a.created_at) = '2024-01-07'
            $comparacionVentaVendedores = DB::select('SELECT a.idPuntoVenta, a.puntoventa, date(a.created_at) fecha, vendedor, c.nombre tipoPago, SUM(d.importe) monto, t.total FROM tbl_recibos as a LEFT JOIN (SELECT date(b.created_at) fecha, b.idUsuario, SUM(b.total) total FROM `tbl_recibos` as b GROUP by date(b.created_at), b.idUsuario) t on t.fecha = date(a.created_at) and t.idUsuario = a.idUsuario LEFT JOIN tbl_recibo_medio_pago d ON d.idRecibo = a.id INNER JOIN tbl_tipos_pago as c ON c.id = d.idMedioPago GROUP by date(a.created_at), vendedor, c.nombre, a.idUsuario, t.total order by date(a.created_at) desc;');
        } else {
            $comparacionVentaVendedores = DB::select('SELECT a.idPuntoVenta, a.puntoventa, date(a.created_at) fecha, vendedor, c.nombre tipoPago, SUM(d.importe) monto, t.total FROM tbl_recibos as a LEFT JOIN (SELECT date(b.created_at) fecha, b.idUsuario, SUM(b.total) total FROM `tbl_recibos` as b GROUP by date(b.created_at), b.idUsuario) t on t.fecha = date(a.created_at) and t.idUsuario = a.idUsuario LEFT JOIN tbl_recibo_medio_pago d ON d.idRecibo = a.id INNER JOIN tbl_tipos_pago as c ON c.id = d.idMedioPago WHERE a.idPuntoVenta = '.$id.' GROUP by date(a.created_at), vendedor, c.nombre, a.idUsuario, t.total order by date(a.created_at) desc;');
        }

        $data = [
            'comparacionVentaVendedores' => $comparacionVentaVendedores,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function reporteProductosPuntoVenta($id)
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

        // where date(a.created_at) = '2024-01-07'
        $productosPuntoVenta = DB::select("SELECT prod.id, prod.nombreCategoria as 'CATEGORIA', prod.codigoBarra as 'CODIGO BARRA', prod.nombre as 'PRODUCTO', sum(case when prod2.nombrePuntoVenta = 'JOVITA'  then prod2.stockActual end) as 'JOVITA - CANTIDAD', sum(case when prod2.nombrePuntoVenta = 'JOVITA'  then prod2.precioCompra end) as 'JOVITA - PRECIO COMPRA', sum(case when prod2.nombrePuntoVenta = 'JOVITA 2'  then prod2.stockActual end) as 'JOVITA 2 - CANTIDAD', sum(case when prod2.nombrePuntoVenta = 'JOVITA 2'  then prod2.precioCompra end) as 'JOVITA 2 - PRECIO COMPRA', sum(case when prod2.nombrePuntoVenta = 'JOVITA 3'  then prod2.stockActual end) as 'JOVITA 3 - CANTIDAD', sum(case when prod2.nombrePuntoVenta = 'JOVITA 3'  then prod2.precioCompra end) as 'JOVITA 3 - PRECIO COMPRA', sum(case when prod2.nombrePuntoVenta = 'JOVITA GENERAL'  then prod2.stockActual end) as 'JOVITA GENERAL - CANTIDAD', sum(case when prod2.nombrePuntoVenta = 'JOVITA GENERAL'  then prod2.precioCompra end) as 'JOVITA GENERAL - PRECIO COMPRA', (sum(case when prod2.nombrePuntoVenta = 'JOVITA'  then prod2.stockActual end) + sum(case when prod2.nombrePuntoVenta = 'JOVITA 2'  then prod2.stockActual end) + sum(case when prod2.nombrePuntoVenta = 'JOVITA 3'  then prod2.stockActual end) + sum(case when prod2.nombrePuntoVenta = 'JOVITA GENERAL'  then prod2.stockActual end)) as 'TOTAL PRODUCTOS' FROM tbl_productos prod inner join tbl_productos prod2 on prod.id = prod2.id where prod.status = 1 AND prod.idPuntoVenta <> 9 group by prod.nombre order by prod.codigoBarra,prod.nombre;");
        // ". $id ."

        $data = [
            'productosPuntoVenta' => $productosPuntoVenta,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function gananciaTiendas(Request $request)
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
        $params = $request->all();

        $whereFecha = '';

        if (!empty($params['fechaInicio']) && !empty($params['fechaFin'])) {
            $whereFecha = "AND DATE(a.created_at) BETWEEN DATE('".$params['fechaInicio']."') AND DATE('".$params['fechaFin']."')";
        }

        if ($params['idPuntoVenta'] == '10') {
            $sql = "
                SELECT
                    a.puntoventa,
                    DATE(a.created_at) AS fecha,
                    SUM(a.total) AS venta,
                    b.compra AS compra,
                    (SUM(a.total) - b.compra) AS ganancia
                FROM tbl_recibos a
                LEFT JOIN (
                    SELECT
                        a.idPuntoVenta,
                        DATE(a.created_at) AS fecha,
                        SUM(b.cantidad * b.precioCompra) AS compra
                    FROM tbl_recibos a
                    INNER JOIN tbl_recibo_detalles b ON a.id = b.idRecibo
                    WHERE a.idPuntoVenta IN (6, 7, 8, 11)
                    GROUP BY a.idPuntoVenta, DATE(a.created_at)
                ) b ON a.idPuntoVenta = b.idPuntoVenta and DATE(a.created_at) = DATE(b.fecha)
                WHERE a.idPuntoVenta IN (6, 7, 8, 11)
                $whereFecha
                GROUP BY a.idPuntoVenta, DATE(a.created_at), b.compra
                ORDER BY DATE(a.created_at) DESC, a.idPuntoVenta
            ";
            $ventasTotales = DB::select($sql);
        } else {
            $sql = '
            SELECT
                a.puntoventa,
                DATE(a.created_at) AS fecha,
                SUM(a.total) AS venta,
                b.compra AS compra,
                (SUM(a.total) - b.compra) AS ganancia
            FROM tbl_recibos a
            LEFT JOIN (
                SELECT
                    DATE(a.created_at) AS fecha,
                    SUM(b.cantidad * b.precioCompra) AS compra
                FROM tbl_recibos a
                INNER JOIN tbl_recibo_detalles b ON a.id = b.idRecibo
                WHERE a.idPuntoVenta = '.$params['idPuntoVenta'].'
                GROUP BY DATE(a.created_at)
            ) b ON DATE(a.created_at) = DATE(b.fecha)
            WHERE a.idPuntoVenta = '.$params['idPuntoVenta']."
            $whereFecha
            GROUP BY DATE(a.created_at), b.compra, a.idPuntoVenta
            ORDER BY DATE(a.created_at) DESC
            ";

            $ventasTotales = DB::select($sql);
        }

        $data = [
            'ventasTotales' => $ventasTotales,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function valorizacionProductosTienda($id)
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

        if ($id == 0) {
            $reportes = DB::select("SELECT nombrePuntoVenta, codigoBarra, nombre, stockActual, precioCompra, (stockActual * precioCompra) as 'valorizado' FROM `tbl_productos` WHERE status = 1 and idPuntoVenta <> 9 order by codigoBarra, nombre, nombrePuntoVenta;");
        } elseif ($id == '10') {
            $reportes = DB::select("SELECT nombrePuntoVenta, codigoBarra, nombre, stockActual, precioCompra, (stockActual * precioCompra) as 'valorizado' FROM `tbl_productos` WHERE status = 1 and idPuntoVenta in (6, 7, 8, 11) order by codigoBarra, nombre, nombrePuntoVenta;");
        } else {
            $reportes = DB::select("SELECT nombrePuntoVenta, codigoBarra, nombre, stockActual, precioCompra, (stockActual * precioCompra) as 'valorizado' FROM `tbl_productos` WHERE status = 1 and idPuntoVenta = ".$id.' order by codigoBarra, nombre, nombrePuntoVenta;');
        }

        $data = [
            'reportes' => $reportes,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function flujoInversion($id)
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

        if ($id == 0) {
            $reportes = DB::select('SELECT a.idPuntoVenta, a.puntoventa, date(a.fechaEmision) Fecha , sum(a.total) Ventas, m.compra Compras, (sum(a.total) - m.compra) DIferencia FROM tbl_recibos a left join (SELECT c.idPuntoVenta, date(c.fechaCompra) fecha, sum(c.totalCompras) Compra FROM tbl_compras c where c.idPuntoVenta <> 9 group by date(c.fechaCompra), c.idPuntoVenta) m on date(a.fechaEmision) = m.fecha and a.idPuntoVenta = m.idPuntoVenta where a.idPuntoVenta <> 9 group by date(a.fechaEmision), a.idPuntoVenta order by date(a.fechaEmision) desc;');
        } else {
            if ($id == '10') {
                $reportes = DB::select('SELECT a.idPuntoVenta, a.puntoventa, date(a.fechaEmision) Fecha , sum(a.total) Ventas, m.compra Compras, (sum(a.total) - m.compra) DIferencia FROM tbl_recibos a left join (SELECT c.idPuntoVenta, date(c.fechaCompra) fecha, sum(c.totalCompras) Compra FROM tbl_compras c where c.idPuntoVenta in (6, 7, 8, 11) group by date(c.fechaCompra), c.idPuntoVenta) m on date(a.fechaEmision) = m.fecha and a.idPuntoVenta = m.idPuntoVenta where a.idPuntoVenta in (6, 7, 8, 11) group by date(a.fechaEmision), a.idPuntoVenta order by date(a.fechaEmision) desc;');
            } else {
                $reportes = DB::select('SELECT a.idPuntoVenta, a.puntoventa, date(a.fechaEmision) Fecha , sum(a.total) Ventas, m.compra Compras, (sum(a.total) - m.compra) DIferencia FROM tbl_recibos a left join (SELECT c.idPuntoVenta, date(c.fechaCompra) fecha, sum(c.totalCompras) Compra FROM tbl_compras c where c.idPuntoVenta = '.$id.' group by date(c.fechaCompra), c.idPuntoVenta) m on date(a.fechaEmision) = m.fecha and a.idPuntoVenta = m.idPuntoVenta where a.idPuntoVenta = '.$id.' group by date(a.fechaEmision), a.idPuntoVenta order by date(a.fechaEmision) desc;');
            }
        }
        $data = [
            'reportes' => $reportes,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function comprasResumidas(Request $request)
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
        $params = $request->all();

        // $whereFecha = '';
        // if (!empty($params['fechaInicio']) && !empty($params['fechaFin'])) {
        //     $fechaInicio = date('Y-m-d', strtotime($params['fechaInicio']));
        //     $fechaFin = date('Y-m-d', strtotime($params['fechaFin']));
        //     $whereFecha = " AND DATE_FORMAT(a.created_at, '%Y-%m-%d') BETWEEN '$fechaInicio' AND '$fechaFin'";
        // }

        if (empty($params['idProveedor'])) {
            if ($params['idPuntoVenta'] == '10') {
                $compras = DB::select("SELECT month(date(fechaCompra)) as 'mes',  year(date(fechaCompra)) as 'ano', c.nombre as 'puntoVenta',  rucProveedor, nombreProveedor, sum(totalCompras) as totalCompras, fechaCompra FROM `tbl_compras` a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where idPuntoVenta in (6, 7, 8, 11) GROUP BY month(date(fechaCompra)),  year(date(fechaCompra)), rucProveedor, nombreProveedor ORDER BY year(date(fechaCompra)) desc, month(date(fechaCompra)) desc, sum(totalCompras) desc;");
            } else {
                $compras = DB::select("SELECT month(date(fechaCompra)) as 'mes',  year(date(fechaCompra)) as 'ano', c.nombre as 'puntoVenta',  rucProveedor, nombreProveedor, sum(totalCompras) as totalCompras, fechaCompra FROM `tbl_compras` a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where idPuntoVenta = ".$params['idPuntoVenta'].' GROUP BY month(date(fechaCompra)),  year(date(fechaCompra)), rucProveedor, nombreProveedor ORDER BY year(date(fechaCompra)) desc, month(date(fechaCompra)) desc, sum(totalCompras) desc;');
            }
        } else {
            if ($params['idPuntoVenta'] == '10') {
                $compras = DB::select("SELECT month(date(fechaCompra)) as 'mes',  year(date(fechaCompra)) as 'ano', c.nombre as 'puntoVenta',  rucProveedor, nombreProveedor, sum(totalCompras) as totalCompras, fechaCompra FROM `tbl_compras` a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where idPuntoVenta in (6, 7, 8, 11) AND idProveedor = ".$params['idProveedor'].' GROUP BY month(date(fechaCompra)),  year(date(fechaCompra)), rucProveedor, nombreProveedor ORDER BY year(date(fechaCompra)) desc, month(date(fechaCompra)) desc, sum(totalCompras) desc;');
            } else {
                $compras = DB::select("SELECT month(date(fechaCompra)) as 'mes',  year(date(fechaCompra)) as 'ano', c.nombre as 'puntoVenta',  rucProveedor, nombreProveedor, sum(totalCompras) as totalCompras, fechaCompra FROM `tbl_compras` a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where idPuntoVenta = ".$params['idPuntoVenta'].' AND idProveedor = '.$params['idProveedor'].' GROUP BY month(date(fechaCompra)),  year(date(fechaCompra)), rucProveedor, nombreProveedor ORDER BY year(date(fechaCompra)) desc, month(date(fechaCompra)) desc, sum(totalCompras) desc;');
            }
        }

        $data = [
            'compras' => $compras,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function ventasComprasDiarias(Request $request)
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
        $params = $request->all();

        $whereFecha = '';
        if (!empty($params['fechaInicio']) && !empty($params['fechaFin'])) {
            $fechaInicio = date('Y-m-d', strtotime($params['fechaInicio']));
            $fechaFin = date('Y-m-d', strtotime($params['fechaFin']));
            $whereFecha = " AND DATE_FORMAT(M.FECHA, '%Y-%m-%d') BETWEEN '$fechaInicio' AND '$fechaFin'";
        }

        if ($params['idPuntoVenta'] == '10') {
            $sql = "select M.PUNTO_VENTA as puntoventa, M.FECHA as fechaEmision, N.COMPRADO as totalCompras, M.VENDIDO as totalVentas FROM (select c.nombre as 'PUNTO_VENTA', a.idPuntoVenta, date(a.fechaEmision) as 'FECHA', sum(a.total) as 'VENDIDO' from tbl_recibos a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where a.status = 1 group by date(a.fechaEmision), c.nombre)M left JOIN (SELECT c.nombre as 'PUNTO_VENTA', a.idPuntoVenta ,date(a.fechaCompra) as 'FECHA', sum(a.totalCompras) as 'COMPRADO' FROM tbl_compras a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where a.status = 1 group by date(a.fechaCompra), c.nombre)N ON M.FECHA = N.FECHA and M.PUNTO_VENTA = N.PUNTO_VENTA WHERE M.idPuntoVenta in (6, 7, 8, 11) $whereFecha ORDER BY M.FECHA DESC;";
        } else {
            $sql = "select M.PUNTO_VENTA as puntoventa, M.FECHA as fechaEmision, N.COMPRADO as totalCompras, M.VENDIDO as totalVentas FROM (select c.nombre as 'PUNTO_VENTA', a.idPuntoVenta, date(a.fechaEmision) as 'FECHA', sum(a.total) as 'VENDIDO' from tbl_recibos a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where a.status = 1 group by date(a.fechaEmision), c.nombre)M left JOIN (SELECT c.nombre as 'PUNTO_VENTA', a.idPuntoVenta ,date(a.fechaCompra) as 'FECHA', sum(a.totalCompras) as 'COMPRADO' FROM tbl_compras a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where a.status = 1 group by date(a.fechaCompra), c.nombre)N ON M.FECHA = N.FECHA and M.PUNTO_VENTA = N.PUNTO_VENTA WHERE M.idPuntoVenta = ".$params['idPuntoVenta']." $whereFecha ORDER BY M.FECHA DESC;";
        }

        $compras = DB::select($sql);

        $data = [
            'compras' => $compras,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function sobranteVsFaltantes(Request $request)
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
        $params = $request->all();

        $whereFecha = '';
        if (!empty($params['fechaInicio']) && !empty($params['fechaFin'])) {
            $fechaInicio = date('Y-m-d', strtotime($params['fechaInicio']));
            $fechaFin = date('Y-m-d', strtotime($params['fechaFin']));
            $whereFecha = " AND DATE_FORMAT(M.FECHA, '%Y-%m-%d') BETWEEN '$fechaInicio' AND '$fechaFin'";
        }

        if ($params['idPuntoVenta'] == '10') {
            $sql = "select M.PUNTO_VENTA as puntoventa, M.FECHA as fecha, IFNULL(M.MONTO_SOBRANTE,0) as montoSobrante, IFNULL(N.MONTO_FALTANTE,0) as montoFaltante, (IFNULL(M.MONTO_SOBRANTE,0) - IFNULL(N.MONTO_FALTANTE,0)) as diferencia FROM (SELECT TRIM(c.nombre) as 'PUNTO_VENTA', a.idPuntoVenta ,date(a.fecha) as 'FECHA', IFNULL(sum(a.ingresoSobrante), 0) as 'MONTO_SOBRANTE', 0 as 'MONTO_FALTANTE' FROM tbl_cierrecaja a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where a.ingresoSobrante != 0 group by date(a.fecha), c.nombre)M left JOIN (select TRIM(c.nombre) as 'PUNTO_VENTA', a.idPuntoVenta, date(a.fecha) as 'FECHA', 0 as 'MONTO_SOBRANTE', IFNULL(sum(a.total), 0) AS 'MONTO_FALTANTE' from tbl_productos_faltantes a inner join tbl_punto_venta c on a.idPuntoVenta = c.id group by date(a.fecha), c.nombre)N ON M.FECHA = N.FECHA and M.idPuntoVenta = N.idPuntoVenta WHERE M.idPuntoVenta in (6, 7, 8, 11) $whereFecha ORDER BY M.FECHA DESC;";
        } else {
            $sql = "select M.PUNTO_VENTA as puntoventa, M.FECHA as fecha, IFNULL(M.MONTO_SOBRANTE,0) as montoSobrante, IFNULL(N.MONTO_FALTANTE,0) as montoFaltante, (IFNULL(M.MONTO_SOBRANTE,0) - IFNULL(N.MONTO_FALTANTE,0)) as diferencia FROM (SELECT TRIM(c.nombre) as 'PUNTO_VENTA', a.idPuntoVenta ,date(a.fecha) as 'FECHA', IFNULL(sum(a.ingresoSobrante), 0) as 'MONTO_SOBRANTE', 0 as 'MONTO_FALTANTE' FROM tbl_cierrecaja a inner join tbl_punto_venta c on a.idPuntoVenta = c.id where a.ingresoSobrante != 0 group by date(a.fecha), c.nombre)M left JOIN (select TRIM(c.nombre) as 'PUNTO_VENTA', a.idPuntoVenta, date(a.fecha) as 'FECHA', 0 as 'MONTO_SOBRANTE', IFNULL(sum(a.total), 0) AS 'MONTO_FALTANTE' from tbl_productos_faltantes a inner join tbl_punto_venta c on a.idPuntoVenta = c.id group by date(a.fecha), c.nombre)N ON M.FECHA = N.FECHA and M.idPuntoVenta = N.idPuntoVenta WHERE M.idPuntoVenta = ".$params['idPuntoVenta']." $whereFecha ORDER BY M.FECHA DESC;";
        }

        $reportes = DB::select($sql);

        $data = [
            'reportes' => $reportes,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function proveedoresProductos($id)
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

        if ($id == '10') {
            $productosProveedor = ProductosProveedor::whereIn('idPuntoVenta', [6, 7, 8, 11])->get();
        } else {
            $productosProveedor = ProductosProveedor::where('idPuntoVenta', $id)->get();
        }

        $data = [
            'productosProveedor' => $productosProveedor,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function productosStockMinimo($id)
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

        // Definir stock mínimo estandarizado
        $stockMinimo = 5;
        $stockAlerta = 10;

        if ($id == '10') {
            $sql = "SELECT
                        pv.nombre as puntoVenta,
                        c.nombre as categoria,
                        p.codigoBarra,
                        p.nombre as nombreProducto,
                        {$stockMinimo} as stockMinimo,
                        p.stockActual
                    FROM tbl_productos p
                    INNER JOIN tbl_categorias c ON p.idCategoria = c.id
                    INNER JOIN tbl_punto_venta pv ON p.idPuntoVenta = pv.id
                    WHERE p.status = 1
                    AND p.stockActual < {$stockMinimo}
                    AND p.idPuntoVenta IN (6, 7, 8, 11)
                    ORDER BY p.stockActual ASC, pv.nombre, c.nombre, p.nombre";
        } else {
            $sql = "SELECT
                        pv.nombre as puntoVenta,
                        c.nombre as categoria,
                        p.codigoBarra,
                        p.nombre as nombreProducto,
                        {$stockMinimo} as stockMinimo,
                        p.stockActual
                    FROM tbl_productos p
                    INNER JOIN tbl_categorias c ON p.idCategoria = c.id
                    INNER JOIN tbl_punto_venta pv ON p.idPuntoVenta = pv.id
                    WHERE p.status = 1
                    AND p.stockActual < {$stockMinimo}
                    AND p.idPuntoVenta = {$id}
                    ORDER BY p.stockActual ASC, c.nombre, p.nombre";
        }

        $productosStockMinimo = DB::select($sql);

        $data = [
            'productosStockMinimo' => $productosStockMinimo,
            'stockMinimo' => $stockMinimo,
            'stockAlerta' => $stockAlerta,
            'totalProductos' => count($productosStockMinimo),
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function ventasCategoriaDetallado(Request $request)
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

        $params = $request->all();

        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $perPage = isset($params['perPage']) ? (int) $params['perPage'] : 10;

        if ($page < 1) {
            $page = 1;
        }

        if ($perPage < 1) {
            $perPage = 10;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $offset = ($page - 1) * $perPage;

        $fechaWhere = '';
        if (!empty($params['fechaInicio']) && !empty($params['fechaFin'])) {
            $fechaInicio = date('Y-m-d', strtotime($params['fechaInicio']));
            $fechaFin = date('Y-m-d', strtotime($params['fechaFin']));
            $fechaWhere = " AND date(a.fechaEmision) BETWEEN '".$fechaInicio."' AND '".$fechaFin."'";
        }

        if (isset($params['idPuntoVenta']) && $params['idPuntoVenta'] == '10') {
            $baseSql = 'SELECT a.idPuntoVenta IDPUNTOVENTA, a.puntoventa PUNTOVENTA, date(a.fechaEmision) FECHA, c.nombreCategoria CATEGORIA, b.codigoBarra CODIGOBARRA, b.nombre PRODUCTO, b.precio PRECIO, sum(b.cantidad) CANTIDADVENDIDA, sum(b.total) TOTAL FROM `tbl_recibos` a inner join `tbl_recibo_detalles` b on a.id = b.idRecibo inner join `tbl_productos` c on b.idProducto = c.id where a.idPuntoVenta IN (6,7,8,11) AND a.status = 1 and c.status = 1'.$fechaWhere.' group by date(a.fechaEmision), b.codigoBarra, b.nombre';
        } else {
            $idPto = isset($params['idPuntoVenta']) ? $params['idPuntoVenta'] : 0;

            $baseSql = 'SELECT a.idPuntoVenta IDPUNTOVENTA, a.puntoventa PUNTOVENTA, date(a.fechaEmision) FECHA, c.nombreCategoria CATEGORIA, b.codigoBarra CODIGOBARRA, b.nombre PRODUCTO, b.precio PRECIO, sum(b.cantidad) CANTIDADVENDIDA, sum(b.total) TOTAL FROM `tbl_recibos` a inner join `tbl_recibo_detalles` b on a.id = b.idRecibo inner join `tbl_productos` c on b.idProducto = c.id where a.idPuntoVenta = '.$idPto.' AND a.status = 1 and c.status = 1'.$fechaWhere.' group by date(a.fechaEmision), b.codigoBarra, b.nombre';
        }

        $totalRowsResult = DB::select('SELECT COUNT(*) as total FROM ('.$baseSql.') x');
        $total = (int) ($totalRowsResult[0]->total ?? 0);

        $sql = $baseSql.' order by CATEGORIA ASC, FECHA ASC, PRODUCTO ASC LIMIT '.$perPage.' OFFSET '.$offset;
        $reportes = DB::select($sql);

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = [
            'ventasCategoriaDetallado' => $reportes,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }
}
