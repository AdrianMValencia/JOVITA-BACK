<?php

namespace App\Http\Controllers;

use App\Models\VentasTotales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class VentasTotalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function show($id)
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

        if ($ventasTotales = VentasTotales::where('idPuntoVenta', $id)->get()->load('puntoventas')) {
            $data = array(
                'ventasTotales' => $ventasTotales,
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

    public function ventasMes(Request $request){

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

            $params_array = $request->all();
            $params = (object)$params_array;

        $whereFecha = '';

        if (!empty($params->fechaInicio) && !empty($params->fechaFin)) {
            $whereFecha = "AND DATE(a.created_at) BETWEEN DATE('" . $params->fechaInicio . "') AND DATE('" . $params->fechaFin . "')";
        }

        if ($params->idPuntoVenta == '10') {

            $sql = "
                SELECT m.idPuntoVenta, m.puntoventa, m.fecha, m.venta, n.Compra as compra, (m.venta - n.Compra) as ganancia from (SELECT a.idPuntoVenta, a.puntoventa, date(a.fechaEmision) fecha , sum(a.total) venta FROM tbl_recibos a where a.status = 1 group by date(a.fechaEmision), a.idPuntoVenta )m left join (SELECT a.idPuntoVenta, a.puntoventa, date(a.fechaEmision) fecha, sum(b.cantidad * b.precioCompra) Compra FROM tbl_recibos a left join tbl_recibo_detalles b on a.id = b.idRecibo where a.status = 1 group by date(a.fechaEmision), a.idPuntoVenta )n on m.fecha= n.fecha and m.idPuntoVenta = n.idPuntoVenta
                $whereFecha
                order by m.fecha desc, m.idPuntoVenta
            ";
            $ventasTotales = DB::select($sql);
        }else{

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
                    DATE(a.created_at) AS fecha,
                    SUM(b.cantidad * b.precioCompra) AS compra
                FROM tbl_recibos a
                INNER JOIN tbl_recibo_detalles b ON a.id = b.idRecibo
                WHERE a.idPuntoVenta = " . $params->idPuntoVenta . "
                GROUP BY DATE(a.created_at), a.idPuntoVenta
            ) b ON DATE(a.created_at) = DATE(b.fecha)
            WHERE a.idPuntoVenta = " . $params->idPuntoVenta . "
            $whereFecha
            GROUP BY DATE(a.created_at), b.compra, a.idPuntoVenta
            ORDER BY DATE(a.created_at) DESC
            ";

            $ventasTotales = DB::select($sql);
        }
        $data = array(
            'ventasTotales' => $ventasTotales,
            'status' => 200
        );
        return response()->json($data, $data['status']);
    }

    public function ventasMeNews(Request $request){

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

        if (($params['idPuntoVenta'] ?? null) == '10') {
            $ventasTotales = DB::select("SELECT DAY(created_at) AS dia, SUM(CASE WHEN YEAR(created_at) = ". $params['anioDesde'] ." THEN total ELSE 0 END) AS ventas1, SUM(CASE WHEN YEAR(created_at) = ". $params['anioHasta'] ." THEN total ELSE 0 END) AS ventas2 FROM tbl_recibos WHERE MONTH(created_at) = ". $params['mesDesde'] ." AND YEAR(created_at) IN (". $params['anioDesde'] .", ". $params['anioHasta'] .") AND idPuntoVenta in(6, 7, 8, 11) GROUP BY dia ORDER BY dia desc;");
        }else{
            $ventasTotales = DB::select("SELECT DAY(created_at) AS dia, SUM(CASE WHEN YEAR(created_at) = ". $params['anioDesde'] ." THEN total ELSE 0 END) AS ventas1, SUM(CASE WHEN YEAR(created_at) = ". $params['anioHasta'] ." THEN total ELSE 0 END) AS ventas2 FROM tbl_recibos WHERE MONTH(created_at) = ". $params['mesDesde'] ." AND YEAR(created_at) IN (". $params['anioDesde'] .", ". $params['anioHasta'] .") AND idPuntoVenta = ". $params['idPuntoVenta'] ." GROUP BY dia ORDER BY dia desc;");
        }
        $data = array(
            'ventasTotales' => $ventasTotales,
            'status' => 200
        );
        return response()->json($data, $data['status']);
    }

    public function ventasAnio(Request $request)
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

        // Obtener el año si existe fechaInicio válida
        $anio = null;
        if (!empty($params['fechaInicio']) && strtotime($params['fechaInicio'])) {
            $anio = date('Y', strtotime($params['fechaInicio']));
        }

        // Agregar condición de año solo si existe
        $whereAnio = '';
        if (!is_null($anio)) {
            $whereAnio = "AND YEAR(a.fechaEmision) = $anio";
        }

        if (($params['idPuntoVenta'] ?? null) == '10') {
            $ventasTotales = DB::select("
                SELECT a.puntoventa,YEAR(a.fechaEmision) AS ano,MONTH(a.fechaEmision) AS mes,SUM(a.total) AS venta,
                m.compra AS compra,
                n.pago AS pagos,
                (SUM(a.total) - n.pago - m.compra) AS Ganancia
                FROM tbl_recibos a
                LEFT JOIN (SELECT a.idPuntoVenta,YEAR(a.fechaEmision) AS ano,MONTH(a.fechaEmision) AS mes,SUM(b.cantidad * b.precioCompra) AS Compra
                        FROM tbl_recibos a
                        LEFT JOIN tbl_recibo_detalles b ON a.id = b.idRecibo
                        WHERE a.idPuntoVenta IN (6, 7, 8, 11)
                        GROUP BY a.idPuntoVenta, YEAR(a.fechaEmision), MONTH(a.fechaEmision)
                ) m ON a.idPuntoVenta = m.idPuntoVenta and YEAR(a.fechaEmision) = m.ano AND MONTH(a.fechaEmision) = m.mes

                LEFT JOIN (SELECT a.idPuntoVenta,YEAR(c.fechaVencimiento) AS ano,MONTH(c.fechaVencimiento) AS mes, SUM(c.total) AS pago
                        FROM tbl_pagos_realizar a
                        LEFT JOIN tbl_pagos_realizar_detalle c ON a.id = c.idPagoRealizar
                        WHERE a.idPuntoVenta IN (6, 7, 8, 11)
                        GROUP BY a.idPuntoVenta, YEAR(c.fechaVencimiento), MONTH(c.fechaVencimiento)
                ) n ON a.idPuntoVenta = n.idPuntoVenta and YEAR(a.fechaEmision) = n.ano AND MONTH(a.fechaEmision) = n.mes
                WHERE a.idPuntoVenta IN (6, 7, 8, 11)
                $whereAnio
                GROUP BY YEAR(a.fechaEmision), MONTH(a.fechaEmision), a.idPuntoVenta
                order by YEAR(a.fechaEmision) desc, MONTH(a.fechaEmision) desc, a.idPuntoVenta asc;
            ");
        } else {
            $idPuntoVenta = $params['idPuntoVenta'] ?? '';
            $ventasTotales = DB::select("
                SELECT a.puntoventa,YEAR(a.fechaEmision) AS ano,MONTH(a.fechaEmision) AS mes,SUM(a.total) AS venta,
                m.compra AS compra,
                n.pago AS pagos,
                (SUM(a.total) - n.pago - m.compra) AS Ganancia
                FROM tbl_recibos a
                LEFT JOIN (SELECT a.idPuntoVenta,YEAR(a.fechaEmision) AS ano,MONTH(a.fechaEmision) AS mes,SUM(b.cantidad * b.precioCompra) AS Compra
                        FROM tbl_recibos a
                        LEFT JOIN tbl_recibo_detalles b ON a.id = b.idRecibo
                        WHERE a.idPuntoVenta = $idPuntoVenta
                        GROUP BY a.idPuntoVenta, YEAR(a.fechaEmision), MONTH(a.fechaEmision)
                ) m ON a.idPuntoVenta = m.idPuntoVenta and YEAR(a.fechaEmision) = m.ano AND MONTH(a.fechaEmision) = m.mes

                LEFT JOIN (SELECT a.idPuntoVenta,YEAR(c.fechaVencimiento) AS ano,MONTH(c.fechaVencimiento) AS mes, SUM(c.total) AS pago
                        FROM tbl_pagos_realizar a
                        LEFT JOIN tbl_pagos_realizar_detalle c ON a.id = c.idPagoRealizar
                        WHERE a.idPuntoVenta = $idPuntoVenta
                        GROUP BY a.idPuntoVenta, YEAR(c.fechaVencimiento), MONTH(c.fechaVencimiento)
                ) n ON a.idPuntoVenta = n.idPuntoVenta and YEAR(a.fechaEmision) = n.ano AND MONTH(a.fechaEmision) = n.mes
                WHERE a.idPuntoVenta = $idPuntoVenta
                $whereAnio
                GROUP BY YEAR(a.fechaEmision), MONTH(a.fechaEmision), a.idPuntoVenta
                order by YEAR(a.fechaEmision) desc, MONTH(a.fechaEmision) desc, a.idPuntoVenta asc;
            ");
        }

        $data = array(
            'ventasTotales' => $ventasTotales,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function ventasAnioNew(Request $request)
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

        if (($params['idPuntoVenta'] ?? null) == '10') {
            $ventasTotales = DB::select("SELECT YEAR(created_at) AS anio,  MONTH(created_at) AS mes, SUM(total) AS total_ventas FROM tbl_recibos WHERE YEAR(created_at) IN (". $params['anioDesde'] .", ". $params['anioHasta'] .") AND idPuntoVenta in(6, 7, 8, 11) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY anio;");
        }else{
            $ventasTotales = DB::select("SELECT YEAR(created_at) AS anio,  MONTH(created_at) AS mes, SUM(total) AS total_ventas FROM tbl_recibos WHERE YEAR(created_at) IN (". $params['anioDesde'] .", ". $params['anioHasta'] .") AND idPuntoVenta = ". $params['idPuntoVenta'] ." GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY anio;");
        }

        $data = array(
            'ventasTotales' => $ventasTotales,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function reporteMensualporVendedorComponent($id)
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
            $ventasTotales = DB::select("SELECT a.puntoventa, YEAR(a.fechaEmision) ano, MONTH(a.fechaEmision) mes , a.vendedor , sum(a.total) monto  FROM `tbl_recibos` a left join (SELECT YEAR(a.fechaEmision) ano,MONTH(a.fechaEmision) mes, sum(b.cantidad * b.precioCompra) Compra FROM `tbl_recibos` a left join `tbl_recibo_detalles` b on a.id = b.idRecibo where a.idPuntoVenta in(6, 7, 8, 11) group by YEAR(a.fechaEmision),MONTH(a.fechaEmision)) m on YEAR(a.fechaEmision) = m.ano and MONTH(a.fechaEmision) = m.mes left join (SELECT YEAR(c.fechaVencimiento) ano, MONTH(c.fechaVencimiento) mes, sum(c.total) pago FROM `tbl_pagos_realizar` a left join `tbl_pagos_realizar_detalle` c on a.id = c.idPagoRealizar where a.idPuntoVenta in(6, 7, 8, 11) group by YEAR(c.fechaVencimiento),MONTH(c.fechaVencimiento)) n on YEAR(a.fechaEmision) = n.ano and MONTH(a.fechaEmision) = n.mes where a.idPuntoVenta in(6, 7, 8, 11) group by YEAR(a.fechaEmision),MONTH(a.fechaEmision), a.vendedor order by YEAR(a.fechaEmision) desc,MONTH(a.fechaEmision) desc, sum(a.total) desc;");
        }else{

            $ventasTotales = DB::select("SELECT a.puntoventa, YEAR(a.fechaEmision) ano, MONTH(a.fechaEmision) mes , a.vendedor , sum(a.total) monto  FROM `tbl_recibos` a left join (SELECT YEAR(a.fechaEmision) ano,MONTH(a.fechaEmision) mes, sum(b.cantidad * b.precioCompra) Compra FROM `tbl_recibos` a left join `tbl_recibo_detalles` b on a.id = b.idRecibo where a.idPuntoVenta = ". $id ." group by YEAR(a.fechaEmision),MONTH(a.fechaEmision)) m on YEAR(a.fechaEmision) = m.ano and MONTH(a.fechaEmision) = m.mes left join (SELECT YEAR(c.fechaVencimiento) ano, MONTH(c.fechaVencimiento) mes, sum(c.total) pago FROM `tbl_pagos_realizar` a left join `tbl_pagos_realizar_detalle` c on a.id = c.idPagoRealizar where a.idPuntoVenta = ". $id ." group by YEAR(c.fechaVencimiento),MONTH(c.fechaVencimiento)) n on YEAR(a.fechaEmision) = n.ano and MONTH(a.fechaEmision) = n.mes where a.idPuntoVenta = ". $id ." group by YEAR(a.fechaEmision),MONTH(a.fechaEmision), a.vendedor order by YEAR(a.fechaEmision) desc,MONTH(a.fechaEmision) desc, sum(a.total) desc;");
        }

        $data = array(
            'ventasTotales' => $ventasTotales,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }
}
