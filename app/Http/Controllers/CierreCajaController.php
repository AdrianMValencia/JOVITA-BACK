<?php

namespace App\Http\Controllers;

use App\Models\Abastecimientos;
use App\Models\CierreCaja;
use App\Models\PuntoVenta;
use App\Models\Ubigeo;
use App\Models\RecibosMedioPago;
use App\Models\Recibos;
use App\Models\Compras;
use App\Models\Productos;
use App\Models\Categorias;
use App\Models\User;
use App\Models\Cajas;
use App\Models\VentasTotales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Barryvdh\DomPDF\Facade\Pdf;

class CierreCajaController extends Controller
{
    public function index(){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $cierreCajas = CierreCaja::orderBy('fecha', 'desc')->get()->load('usuarios')->load('puntoventa');
            $data = array(
                'cierreCajas' => $cierreCajas,
                'total' => @count($cierreCajas),
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        if ($cierreCajas = CierreCaja::where('idPuntoVenta', $id)->get()) {
            $cierreCajas = CierreCaja::where('idPuntoVenta', $id)->get()->load('usuarios')->load('puntoventa');
            $data = array(
                'cierreCajas' => $cierreCajas,
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

    public function store(Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

            $params = $request->all();

            $cierreCajas = new CierreCaja();
            $cierreCajas->idUsuario = $params['idUsuario'] ?? null;
            $cierreCajas->fecha = $params['fecha'] ?? null;
            $cierreCajas->usuario = $params['usuario'] ?? null;
            $cierreCajas->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $cierreCajas->puntoventa = $params['puntoventa'] ?? null;
            $cierreCajas->tipo = $params['tipo'] ?? null;
            $cierreCajas->inicioCaja = $params['inicioCaja'] ?? null;
            $cierreCajas->entradaDinero = $params['entradaDinero'] ?? null;
            $cierreCajas->entradaTotal = $params['entradaTotal'] ?? null;
            $cierreCajas->salidaDinero = $params['salidaDinero'] ?? null;
            $cierreCajas->pagoProveedores = $params['pagoProveedores'] ?? null;
            $cierreCajas->salidasTotal = $params['salidasTotal'] ?? null;
            $cierreCajas->numeroTicket = $params['numeroTicket'] ?? null;
            $cierreCajas->pagoCreaditos = $params['pagoCreaditos'] ?? null;
            $cierreCajas->motivo = $params['motivo'] ?? null;
            $cierreCajas->idProveedor = $params['idProveedor'] ?? null;
            $cierreCajas->ruc = $params['ruc'] ?? null;
            $cierreCajas->razonSocial = $params['razonSocial'] ?? null;
            $cierreCajas->idCompras = $params['idCompras'] ?? null;
            $cierreCajas->otros = $params['otros'] ?? null;
            $cierreCajas->idMedioPago = $params['idMedioPago'] ?? null;
            $cierreCajas->ingresoSobrante = $params['ingresoSobrante'] ?? null;
            $cierreCajas->ingresoSobranteTotal = $params['ingresoSobranteTotal'] ?? null;
            $cierreCajas->save();

            $data = array(
                'cierreCajas' => $cierreCajas,
                'message' => 'Se registro el cierre de caja correctamente',
                'status' => 200
            );

            return response()->json($data, $data['status']);
    }

    public function update($id, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

            $params = $request->all();

            if($cierreCajas = CierreCaja::find($id)){
                $cierreCajas->idUsuario = $params['idUsuario'] ?? $cierreCajas->idUsuario;
                $cierreCajas->usuario = $params['usuario'] ?? $cierreCajas->usuario;
                $cierreCajas->fecha = $params['fecha'] ?? $cierreCajas->fecha;
                $cierreCajas->idPuntoVenta = $params['idPuntoVenta'] ?? $cierreCajas->idPuntoVenta;
                $cierreCajas->puntoventa = $params['puntoventa'] ?? $cierreCajas->puntoventa;
                $cierreCajas->tipo = $params['tipo'] ?? $cierreCajas->tipo;
                $cierreCajas->inicioCaja = $params['inicioCaja'] ?? $cierreCajas->inicioCaja;
                $cierreCajas->entradaDinero = $params['entradaDinero'] ?? $cierreCajas->entradaDinero;
                $cierreCajas->entradaTotal = $params['entradaTotal'] ?? $cierreCajas->entradaTotal;
                $cierreCajas->salidaDinero = $params['salidaDinero'] ?? $cierreCajas->salidaDinero;
                $cierreCajas->pagoProveedores = $params['pagoProveedores'] ?? $cierreCajas->pagoProveedores;
                $cierreCajas->salidasTotal = $params['salidasTotal'] ?? $cierreCajas->salidasTotal;
                $cierreCajas->numeroTicket = $params['numeroTicket'] ?? $cierreCajas->numeroTicket;
                $cierreCajas->pagoCreaditos = $params['pagoCreaditos'] ?? $cierreCajas->pagoCreaditos;
                $cierreCajas->motivo = $params['motivo'] ?? $cierreCajas->motivo;
                $cierreCajas->idProveedor = $params['idProveedor'] ?? $cierreCajas->idProveedor;
                $cierreCajas->ruc = $params['ruc'] ?? $cierreCajas->ruc;
                $cierreCajas->razonSocial = $params['razonSocial'] ?? $cierreCajas->razonSocial;
                $cierreCajas->idCompras = $params['idCompras'] ?? $cierreCajas->idCompras;
                $cierreCajas->otros = $params['otros'] ?? $cierreCajas->otros;
                $cierreCajas->idMedioPago = $params['idMedioPago'] ?? $cierreCajas->idMedioPago;
                $cierreCajas->ingresoSobrante = $params['ingresoSobrante'] ?? $cierreCajas->ingresoSobrante;
                $cierreCajas->ingresoSobranteTotal = $params['ingresoSobranteTotal'] ?? $cierreCajas->ingresoSobranteTotal;

                unset($params['id']);
                unset($params['created_at']);

                $cierreCajas->save();

                $data = array(
                    'cierreCajas' => $cierreCajas,
                    'message' => 'Registro actualizado correctamente.',
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        if($cierreCajas = CierreCaja::find($id)){

            $cierreCajas->delete();

            $data = array(
                'cierreCajas' => $cierreCajas,
                'message' => 'Registro eliminado correctamente.',
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

    public function guardarGeneral(Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

            $params = $request->all();

            foreach ($params as $value) {
                $cierreCajas = new CierreCaja();
                $cierreCajas->idUsuario = $value['idUsuario'] ?? null;
                $cierreCajas->fecha = $value['fecha'] ?? null;
                $cierreCajas->usuario = $value['usuario'] ?? null;
                $cierreCajas->idPuntoVenta = $value['idPuntoVenta'] ?? null;
                $cierreCajas->puntoventa = $value['puntoventa'] ?? null;
                $cierreCajas->tipo = $value['tipo'] ?? null;
                $cierreCajas->inicioCaja = $value['inicioCaja'] ?? null;
                $cierreCajas->entradaDinero = $value['entradaDinero'] ?? null;
                $cierreCajas->entradaTotal = $value['entradaTotal'] ?? null;
                $cierreCajas->salidaDinero = $value['salidaDinero'] ?? null;
                $cierreCajas->pagoProveedores = $value['pagoProveedores'] ?? null;
                $cierreCajas->salidasTotal = $value['salidasTotal'] ?? null;
                $cierreCajas->numeroTicket = $value['numeroTicket'] ?? null;
                $cierreCajas->pagoCreaditos = $value['pagoCreaditos'] ?? null;
                $cierreCajas->ingresoSobrante = $value['ingresoSobrante'] ?? null;
                $cierreCajas->save();
            }

            $data = array(
                'cierreCajas' => $cierreCajas,
                'message' => 'Se registro el cierre de caja correctamente',
                'status' => 200
            );

            return response()->json($data, $data['status']);
    }

    public function reporteCierreCajaTodos($id, $fecha){
        $cierreCajas = CierreCaja::whereRaw("idPuntoVenta = ". $id ." and DATE_FORMAT(fecha,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."'")->get();
        $recibos = Recibos::whereRaw("idPuntoVenta = ". $id ." and DATE_FORMAT(created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."'")->get();
        $puntoVenta = PuntoVenta::find($id);
        $ubigeo = Ubigeo::find($puntoVenta->idUbigeo);
        $cajas = Cajas::where('idPuntoVenta', $id)->first();

        $compras = DB::select("SELECT DATE(a.created_at) fecha, SUM(a.total) venta, b.compra compra, (SUM(a.total) - b.compra) ganancia FROM tbl_recibos a LEFT JOIN (SELECT date(a.created_at) fecha,SUM(b.cantidad * b.precioCompra) compra FROM tbl_recibos a INNER JOIN tbl_recibo_detalles b on a.id = b.idRecibo  where a.idPuntoVenta = ". $id ." GROUP BY DATE(a.created_at))  b on date(a.created_at) = date(b.fecha) where a.idPuntoVenta= ". $id ." and DATE_FORMAT(a.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."' group by date(a.created_at), b.compra order By date(a.created_at) desc;");

        $medioPago = RecibosMedioPago::selectRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') fechaEmision, SUM(tbl_recibo_medio_pago.importe) total")
        ->join('tbl_recibos', 'tbl_recibo_medio_pago.idRecibo', '=', 'tbl_recibos.id')
        ->join('tbl_tipos_pago', 'tbl_recibo_medio_pago.idMedioPago', '=', 'tbl_tipos_pago.id')
        ->whereRaw("tbl_recibos.idPuntoVenta = ". $id . " and DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) . "'")
        ->groupByRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d')")
        ->get();

        $categorias = Categorias::selectRaw('tbl_categorias.nombre, SUM(tbl_recibo_detalles.total) total')
        ->join('tbl_productos', 'tbl_categorias.id', '=', 'tbl_productos.idCategoria')
        ->join('tbl_recibo_detalles', 'tbl_productos.id', '=', 'tbl_recibo_detalles.idProducto')
        ->join('tbl_recibos', 'tbl_recibo_detalles.idRecibo', '=', 'tbl_recibos.id')
        ->where([['tbl_categorias.idPuntoVenta', $id], ['tbl_recibos.idPuntoVenta', $id]])
        ->whereRaw("DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)). "'")
        ->orderByRaw("SUM(tbl_recibo_detalles.total) desc")
        ->groupBy('tbl_categorias.nombre')
        ->get();

        $inicioCaja = 0;
        $entradaDinero = 0;
        $entradaTotal = 0;
        $salidaDinero = 0;
        $pagoProveedores = 0;
        $salidasTotal = 0;
        $pagoCreaditos = 0;
        $totalGeneral = 0;
        $pagoEfectivo = 0;
        $ventasDia = 0;
        $ingresoSobrante = 0;
        $ingresoSobranteTotal = 0;

        $carbon = new \Carbon\Carbon();
        $date = Carbon::now();
        $date = $date->format('Y-m-d');

        $valorVenta = 0;
        $valorComra = 0;
        $ganancia = 0;
        $cantidadVentasDia = 0;

        foreach ($recibos as $value) {
            $cantidadVentasDia = $cantidadVentasDia + 1;
        }

        foreach ($compras as $value) {
            $valorVenta = $valorVenta + $value->venta;
            $valorComra = $valorComra + $value->compra;
            $ganancia = $ganancia + $value->ganancia;
        }

        foreach ($medioPago as $value) {
            if($value->nombre == 'Efectivo'){
                $pagoEfectivo = $pagoEfectivo + $value->total;
            }
        }

        foreach ($cierreCajas as $value) {
            $inicioCaja = $inicioCaja + $value->inicioCaja;
            $entradaDinero = $entradaDinero + $value->entradaDinero;
            $entradaTotal = $entradaTotal + $value->entradaTotal;
            $salidaDinero = $salidaDinero + $value->salidaDinero;
            $pagoProveedores = $pagoProveedores + $value->pagoProveedores;
            $salidasTotal = $salidasTotal + $value->salidasTotal;
            $pagoCreaditos = $pagoCreaditos + $value->pagoCreaditos;
            $ingresoSobrante = $ingresoSobrante + $value->ingresoSobrante;
            $ingresoSobranteTotal = $ingresoSobranteTotal + $value->ingresoSobranteTotal;
        }

        $abastecimiento = DB::select("SELECT a.created_at, idPuntoVenta, SUM(d.precioCompra * cantidad) as total FROM tbl_abastecimientos a inner join tbl_abastecimientos_detalles d on a.id = d.idAbastecimiento where idPuntoVenta = ". $id ." and DATE_FORMAT(a.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."' group by a.created_at, idPuntoVenta;");

        $totalTraspasoEntreTiendas = 0;
        foreach ($abastecimiento as $value) {
            $totalTraspasoEntreTiendas = $totalTraspasoEntreTiendas + $value->total;
        }

        $abastecimientoDestino = DB::select("SELECT a.created_at, idPuntoVentaNew, SUM(precioCompra * cantidad) as total FROM tbl_abastecimientos a inner join tbl_abastecimientos_detalles d on a.id = d.idAbastecimiento where idPuntoVentaNew = ". $id ." and DATE_FORMAT(a.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."' group by a.created_at, idPuntoVentaNew;");

        $totalTraspasoEntreTiendasDestino = 0;
        foreach ($abastecimientoDestino as $value) {
            $totalTraspasoEntreTiendasDestino = $totalTraspasoEntreTiendasDestino + $value->total;
        }

        $entradaTotal = $entradaTotal + $totalTraspasoEntreTiendas;
        $salidasTotal = $salidasTotal + $totalTraspasoEntreTiendasDestino;
        $totalGeneral = ((($entradaTotal + $pagoEfectivo) - $pagoCreaditos) - $salidasTotal) + $ingresoSobranteTotal;
        $date2 = Carbon::now();
        $fechaTitulo = $date2->format('d/m/Y H:i');
        $idUusario = 0;
        $fechaDia = date('d/m/Y', strtotime($fecha));
        $pdf = Pdf::loadView('cierreCajas', compact('cierreCajas', 'puntoVenta', 'ubigeo', 'inicioCaja', 'entradaDinero', 'entradaTotal', 'salidaDinero', 'pagoProveedores', 'salidasTotal', 'pagoCreaditos', 'totalGeneral', 'pagoEfectivo', 'fechaDia', 'fechaTitulo', 'recibos', 'valorVenta', 'ingresoSobranteTotal', 'valorComra', 'ganancia', 'categorias', 'idUusario', 'cajas', 'cantidadVentasDia', 'medioPago', 'ingresoSobrante', 'totalTraspasoEntreTiendas', 'totalTraspasoEntreTiendasDestino'));
        $pdf = PDF::loadView('cierreCajas', compact('cierreCajas', 'puntoVenta', 'ubigeo', 'inicioCaja', 'entradaDinero', 'entradaTotal', 'salidaDinero', 'pagoProveedores', 'salidasTotal', 'pagoCreaditos', 'totalGeneral', 'pagoEfectivo', 'fechaDia', 'fechaTitulo', 'recibos', 'valorVenta', 'ingresoSobranteTotal', 'valorComra', 'ganancia', 'categorias', 'idUusario', 'cajas', 'cantidadVentasDia', 'medioPago', 'ingresoSobrante', 'totalTraspasoEntreTiendas', 'totalTraspasoEntreTiendasDestino'));

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

        $docHeight = $GLOBALS['bodyHeight'] + 900;
        $pdf->setPaper([0,0,227, $docHeight]);
        // $pdf->setPaper('b7', 'portrait');
        return $pdf->download('CIERRE-DE-CAJA.pdf');
    }

    public function reporteCierreCaja($id, $fecha, $idUusario){
        $cierreCajas = CierreCaja::whereRaw("idUsuario = ". $idUusario . " and idPuntoVenta = ". $id ." and DATE_FORMAT(fecha,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."'")->get();
        $recibos = Recibos::whereRaw("idUsuario = ". $idUusario . " and idPuntoVenta = ". $id ." and DATE_FORMAT(created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."'")->get();
        $usuarios = User::find($idUusario);
        $puntoVenta = PuntoVenta::find($id);
        $ubigeo = Ubigeo::find($puntoVenta->idUbigeo);
        $cajas = Cajas::where('idPuntoVenta', $id)->first();

        $compras = DB::select("SELECT date(a.created_at) as fecha, sum(a.total) as ventas, b.compras as compras, (sum(a.total) - b.compras) as ganancia FROM `tbl_recibos` as a left join (SELECT date(`tbl_recibos`.created_at) as fecha, sum(`tbl_productos`.`precioCompra`) as compras FROM `tbl_recibos` left join `tbl_recibo_detalles` on `tbl_recibos`.`id` = `tbl_recibo_detalles`.`idRecibo` left join `tbl_productos` on `tbl_recibo_detalles`.`idProducto` = `tbl_productos`.`id` where `tbl_recibos`.idPuntoVenta=". $id . " and `tbl_recibos`.idUsuario = ". $idUusario . " group by date(tbl_recibos.created_at)) b on date(a.created_at) = b.fecha where a.idPuntoVenta=". $id . " and a.idUsuario = ". $idUusario . " and DATE_FORMAT(a.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."' group by date(a.created_at), b.compras;");

        $medioPago = RecibosMedioPago::selectRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') fechaEmision, SUM(tbl_recibo_medio_pago.importe) total")
        ->join('tbl_recibos', 'tbl_recibo_medio_pago.idRecibo', '=', 'tbl_recibos.id')
        ->join('tbl_tipos_pago', 'tbl_recibo_medio_pago.idMedioPago', '=', 'tbl_tipos_pago.id')
        ->whereRaw("tbl_recibos.idUsuario = ". $idUusario ." and tbl_recibos.idPuntoVenta = ". $id . " and DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) . "'")
        ->groupByRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d')")
        ->get();

        $categorias = Categorias::selectRaw('tbl_categorias.nombre, SUM(tbl_recibo_detalles.total) total')
        ->join('tbl_productos', 'tbl_categorias.id', '=', 'tbl_productos.idCategoria')
        ->join('tbl_recibo_detalles', 'tbl_productos.id', '=', 'tbl_recibo_detalles.idProducto')
        ->join('tbl_recibos', 'tbl_recibo_detalles.idRecibo', '=', 'tbl_recibos.id')
        ->where([['tbl_categorias.idPuntoVenta', $id], ['tbl_recibos.idPuntoVenta', $id]])
        ->whereRaw("tbl_recibos.idUsuario = ". $idUusario . " and DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)). "'")
        ->orderByRaw("SUM(tbl_recibo_detalles.total) desc")
        ->groupBy('tbl_categorias.nombre')
        ->get();

        $inicioCaja = 0;
        $entradaDinero = 0;
        $entradaTotal = 0;
        $salidaDinero = 0;
        $pagoProveedores = 0;
        $salidasTotal = 0;
        $pagoCreaditos = 0;
        $totalGeneral = 0;
        $pagoEfectivo = 0;
        $ventasDia = 0;
        $ingresoSobrante = 0;
        $ingresoSobranteTotal = 0;

        $carbon = new \Carbon\Carbon();
        $date = Carbon::now();
        $date = $date->format('Y-m-d');

        $valorVenta = 0;
        $valorComra = 0;
        $ganancia = 0;
        $cantidadVentasDia = 0;

        foreach ($recibos as $value) {
            $cantidadVentasDia = $cantidadVentasDia + 1;
        }

        foreach ($compras as $value) {
            $valorVenta = $valorVenta + $value->ventas;
            $valorComra = $valorComra + $value->compras;
            $ganancia = $ganancia + $value->ganancia;
        }

        // foreach ($recibos as $value) {
            //     $valorVenta = $valorVenta + $value->total;
            // }

            foreach ($medioPago as $value) {
                if($value->nombre == 'Efectivo'){
                    $pagoEfectivo = $pagoEfectivo + $value->total;
                }
            }

        foreach ($cierreCajas as $value) {
            $inicioCaja = $inicioCaja + $value->inicioCaja;
            $entradaDinero = $entradaDinero + $value->entradaDinero;
            $entradaTotal = $entradaTotal + $value->entradaTotal;
            $salidaDinero = $salidaDinero + $value->salidaDinero;
            $pagoProveedores = $pagoProveedores + $value->pagoProveedores;
            $salidasTotal = $salidasTotal + $value->salidasTotal;
            $pagoCreaditos = $pagoCreaditos + $value->pagoCreaditos;
            $ingresoSobrante = $ingresoSobrante + $value->ingresoSobrante;
            $ingresoSobranteTotal = $ingresoSobranteTotal + $value->ingresoSobranteTotal;
        }

        $abastecimiento = DB::select("SELECT a.created_at, idPuntoVenta, SUM(d.precioCompra * cantidad) as total FROM tbl_abastecimientos a inner join tbl_abastecimientos_detalles d on a.id = d.idAbastecimiento  where idVendedor = ". $idUusario . " and idPuntoVenta = ". $id ." and DATE_FORMAT(a.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."' group by a.created_at, idPuntoVenta;");

        $totalTraspasoEntreTiendas = 0;
        foreach ($abastecimiento as $value) {
            $totalTraspasoEntreTiendas = $totalTraspasoEntreTiendas + $value->total;
        }

        $abastecimientoDestino = DB::select("SELECT a.created_at, idPuntoVenta, SUM(d.precioCompra * cantidad) as total FROM tbl_abastecimientos a inner join tbl_abastecimientos_detalles d on a.id = d.idAbastecimiento  where idVendedor = ". $idUusario . " and idPuntoVentaNew = ". $id ." and DATE_FORMAT(a.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($fecha)) ."' group by a.created_at, idPuntoVentaNew;");

        $totalTraspasoEntreTiendasDestino = 0;
        foreach ($abastecimientoDestino as $value) {
            $totalTraspasoEntreTiendasDestino = $totalTraspasoEntreTiendasDestino + $value->total;
        }

        $entradaTotal = $entradaTotal + $totalTraspasoEntreTiendas;
        $salidasTotal = $salidasTotal + $totalTraspasoEntreTiendasDestino;
        $totalGeneral = ((($entradaTotal + $pagoEfectivo) - $pagoCreaditos) - $salidasTotal) + $ingresoSobranteTotal;
        $date2 = Carbon::now();
        $fechaTitulo = $date2->format('d/m/Y H:i');
        $fechaDia = date('d/m/Y', strtotime($fecha));
        $pdf = Pdf::loadView('cierreCajas', compact('cierreCajas', 'puntoVenta', 'ubigeo', 'inicioCaja', 'entradaDinero', 'entradaTotal', 'salidaDinero', 'pagoProveedores', 'salidasTotal', 'pagoCreaditos', 'totalGeneral', 'pagoEfectivo', 'fechaDia', 'fechaTitulo', 'recibos', 'valorVenta', 'valorComra', 'ganancia', 'categorias', 'idUusario', 'date', 'usuarios', 'cajas', 'cantidadVentasDia', 'medioPago', 'ingresoSobrante', 'ingresoSobranteTotal', 'totalTraspasoEntreTiendas', 'totalTraspasoEntreTiendasDestino'));
        $pdf = PDF::loadView('cierreCajas', compact('cierreCajas', 'puntoVenta', 'ubigeo', 'inicioCaja', 'entradaDinero', 'entradaTotal', 'salidaDinero', 'pagoProveedores', 'salidasTotal', 'pagoCreaditos', 'totalGeneral', 'pagoEfectivo', 'fechaDia', 'fechaTitulo', 'recibos', 'valorVenta', 'valorComra', 'ganancia', 'categorias', 'idUusario', 'date', 'usuarios', 'cajas', 'cantidadVentasDia', 'medioPago', 'ingresoSobrante', 'ingresoSobranteTotal', 'totalTraspasoEntreTiendas', 'totalTraspasoEntreTiendasDestino'));

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
        return $pdf->download('CIERRE-DE-CAJA.pdf');
    }

    public function guardarReporteVentas(Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

            $params = $request->all();

            $idPuntoVenta = $params['idPuntoVenta'] ?? null;

            $existe = VentasTotales::whereRaw("idPuntoVenta = ". $idPuntoVenta ." AND DATE_FORMAT(fecha, '%Y-%m-%d') = DATE_FORMAT(now(), '%Y-%m-%d')")->get();

            if (count($existe) > 0) {
                $eliminar = VentasTotales::whereRaw("idPuntoVenta = ". $idPuntoVenta ." AND DATE_FORMAT(fecha, '%Y-%m-%d') = DATE_FORMAT(now(), '%Y-%m-%d')")->get();
                foreach ($eliminar as $value) {
                    $value->delete();
                }
            }

            $ventasTotales = DB::select("SELECT date(a.created_at) fecha, SUM(a.total) venta, b.compra compra, (SUM(a.total) - b.compra) ganancia FROM tbl_recibos a LEFT JOIN (SELECT date(a.created_at) fecha, SUM(b.cantidad * b.precioCompra) compra FROM tbl_recibos a INNER JOIN tbl_recibo_detalles b on a.id = b.idRecibo  where a.idPuntoVenta = ". $idPuntoVenta ." GROUP BY date(a.created_at))  b on date(a.created_at) = date(b.fecha) where a.idPuntoVenta= ". $idPuntoVenta ." group by date(a.created_at), b.compra");

            $date = Carbon::now();
            $hora = $date->format('H');

            if ($hora > 21) {
                foreach ($ventasTotales as $value) {
                    $ventas = new VentasTotales();
                    $ventas->fecha = $value->fecha;
                    $ventas->venta = $value->venta;
                    $ventas->compra = $value->compra;
                    $ventas->ganancia = $value->ganancia;
                    $ventas->idPuntoVenta = $idPuntoVenta;
                    $ventas->save();
                }

                $data = array(
                    'ventasTotales' => $ventasTotales,
                    'message' => 'El reporte de ventas del día se genero con éxito.',
                    'activar' => true,
                    'status' => 200
                );

            }else{
                $data = array(
                    'activar' => false,
                    'existe' => $ventasTotales,
                    'message' => 'Fuera de horario para procesar el reporte del día. Horario permitido desde las 9:00 pm.',
                    'status' => 201
                );
            }

            return response()->json($data, $data['status']);
    }

    public function listaIngresoSobrante($id, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $cierreCajas = CierreCaja::where([['idPuntoVenta', $id], ['ingresoSobrante', '>', 0]])->orderBy('fecha', 'desc')->get();
        $data = array(
            'cierreCajas' => $cierreCajas,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }
}
