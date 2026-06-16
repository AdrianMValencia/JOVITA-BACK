<?php

namespace App\Http\Controllers;

use App\Models\Cajas;
use App\Models\Pedidos;
use App\Models\PedidosDetalles;
use App\Models\Productos;
use App\Models\PuntoVenta;
use App\Models\Ubigeo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Barryvdh\DomPDF\Facade\Pdf;

class PedidosController extends Controller
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

        $pedidos = Pedidos::find($id)->orderBy('id', 'desc')->get()->load('puntoventas')->load('detalles');

        $data = array(
            'pedidos' => $pedidos,
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

        $productos = Productos::where([['idPuntoVenta', $id], ['status', 1]])->get();
        $usuarios = User::select('users.*')->join('tbl_puntoventa_user', 'users.id', '=', 'tbl_puntoventa_user.idUser')->where([['tbl_puntoventa_user.idPuntoVenta', $id], ['users.status', 1]])->get();

        if($user->idRol == 1){
            if ($pedidos = Pedidos::where('idPuntoVenta', $id)->get()) {

                $pedidos = Pedidos::where('idPuntoVenta', $id)->orderBy('id', 'desc')->get()->load('detalles')->load('puntoventas');
                $detalles = PedidosDetalles::get();

                $data = array(
                    'pedidos' => $pedidos,
                    'detalles' => $detalles,
                    'productos' => $productos,
                    'usuarios' => $usuarios,
                    'status' => 200
                );

            }else{
                $data = array(
                    'message' => 'Codigo no encontrado',
                    'status' => 404
                );
            }

        }else{

            if ($pedidos = Pedidos::where([['idPuntoVenta', $id], ['status', 1], ['idUsuario', $user->id]])->get()) {

                $pedidos = Pedidos::where([['idPuntoVenta', $id], ['status', 1], ['idUsuario', $user->id]])->orderBy('id', 'desc')->get()->load('detalles')->load('puntoventas');
                $pedidosIds = $pedidos->pluck('id')->all();
                $detalles = PedidosDetalles::whereIn('idPedido', $pedidosIds)->get();

                $data = array(
                    'pedidos' => $pedidos,
                    'detalles' => $detalles,
                    'productos' => $productos,
                    'usuarios' => $usuarios,
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

        $pedidos = new Pedidos();
        $pedidos->idPuntoVenta = $params['idPuntoVenta'] ?? null;
        $pedidos->puntoventa = $params['puntoventa'] ?? null;
        $pedidos->idPuntoVentaLlegada = $params['idPuntoVentaLlegada'] ?? null;
        $pedidos->puntoVentaLlegada = $params['puntoVentaLlegada'] ?? null;
        $pedidos->idUsuario = $params['idUsuario'] ?? null;
        $pedidos->vendedor = $params['vendedor'] ?? null;
        $pedidos->total = $params['total'] ?? null;
        $pedidos->observaciones = $params['observaciones'] ?? null;
        $pedidos->status = $params['status'] ?? null;
        $pedidos->save();

        if (isset($params['detalles']) && is_array($params['detalles'])) {
            foreach ($params['detalles'] as $value) {
                $pedidosDetalles = new PedidosDetalles();
                $pedidosDetalles->idPedido = $pedidos->id;
                $pedidosDetalles->idProducto = $value['idProducto'] ?? null;
                $pedidosDetalles->nombre = $value['nombre'] ?? null;
                $pedidosDetalles->codigoBarra = $value['codigoBarra'] ?? null;
                $pedidosDetalles->precioCompra = $value['precioCompra'] ?? null;
                $pedidosDetalles->tipoPresentacion = $value['tipoPresentacion'] ?? null;
                $pedidosDetalles->cantidadPaquetes = $value['cantidadPaquetes'] ?? null;
                $pedidosDetalles->cantidad = $value['cantidad'] ?? null;
                $pedidosDetalles->total = $value['total'] ?? null;
                $pedidosDetalles->existencia = $value['existencia'] ?? null;
                $pedidosDetalles->save();
            }
        }

        $message = 'Se ha generado el Pedido N' . str_pad($pedidos->id, 4, "0", STR_PAD_LEFT) . ' correctamente';
        $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');

        $data = array(
            'pedidos' => $pedidos,
            'message' => $message,
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
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $params = $request->all();

        if($pedidos = Pedidos::find($id)){
            $pedidos->idPuntoVentaLlegada = $params['idPuntoVentaLlegada'] ?? $pedidos->idPuntoVentaLlegada;
            $pedidos->puntoVentaLlegada = $params['puntoVentaLlegada'] ?? $pedidos->puntoVentaLlegada;
            $pedidos->idUsuario = $params['idUsuario'] ?? $pedidos->idUsuario;
            $pedidos->vendedor = $params['vendedor'] ?? $pedidos->vendedor;
            $pedidos->total = $params['total'] ?? $pedidos->total;
            $pedidos->observaciones = $params['observaciones'] ?? $pedidos->observaciones;
            $pedidos->save();

            if (isset($params['detalles']) && is_array($params['detalles'])) {
                foreach ($params['detalles'] as $value) {
                    if (($value['id'] ?? 0) == 0) {
                        $pedidosDetalles = new PedidosDetalles();
                        $pedidosDetalles->idPedido = $pedidos->id;
                        $pedidosDetalles->idProducto = $value['idProducto'] ?? null;
                        $pedidosDetalles->nombre = $value['nombre'] ?? null;
                        $pedidosDetalles->codigoBarra = $value['codigoBarra'] ?? null;
                        $pedidosDetalles->precioCompra = $value['precioCompra'] ?? null;
                        $pedidosDetalles->tipoPresentacion = $value['tipoPresentacion'] ?? null;
                        $pedidosDetalles->cantidadPaquetes = $value['cantidadPaquetes'] ?? null;
                        $pedidosDetalles->cantidad = $value['cantidad'] ?? null;
                        $pedidosDetalles->total = $value['total'] ?? null;
                        $pedidosDetalles->existencia = $value['existencia'] ?? null;
                        $pedidosDetalles->save();
                    }else{
                        $pedidosDetalles = PedidosDetalles::find($value['id']);
                        if ($pedidosDetalles) {
                            $pedidosDetalles->idPedido = $pedidos->id;
                            $pedidosDetalles->idProducto = $value['idProducto'] ?? $pedidosDetalles->idProducto;
                            $pedidosDetalles->nombre = $value['nombre'] ?? $pedidosDetalles->nombre;
                            $pedidosDetalles->codigoBarra = $value['codigoBarra'] ?? $pedidosDetalles->codigoBarra;
                            $pedidosDetalles->precioCompra = $value['precioCompra'] ?? $pedidosDetalles->precioCompra;
                            $pedidosDetalles->tipoPresentacion = $value['tipoPresentacion'] ?? $pedidosDetalles->tipoPresentacion;
                            $pedidosDetalles->cantidadPaquetes = $value['cantidadPaquetes'] ?? $pedidosDetalles->cantidadPaquetes;
                            $pedidosDetalles->cantidad = $value['cantidad'] ?? $pedidosDetalles->cantidad;
                            $pedidosDetalles->total = $value['total'] ?? $pedidosDetalles->total;
                            $pedidosDetalles->existencia = $value['existencia'] ?? $pedidosDetalles->existencia;
                            $pedidosDetalles->save();
                        }
                    }
                }
            }

            $pedidos->save();

            $message = 'Se ha modificado el Pedido N' . str_pad($pedidos->id, 4, "0", STR_PAD_LEFT) . ' correctamente';
            $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');

            $data = array(
                'pedidos' => $pedidos,
                'message' => $message,
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

        if($pedidos = Pedidos::find($id)){

            $pedidosDetalles = PedidosDetalles::where('idPedido', $id)->get();
            foreach ($pedidosDetalles as $key => $value) {
                $value->delete();
            }

            $pedidos->delete();

            $data = array(
                'pedidos' => $pedidos,
                'message' => 'Pedido eliminado correctamente.',
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

    public function buscarPorFecha(Request $request){

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

        $idPuntoVenta = $params['idPuntoVenta'] ?? null;
        $fechaInicio = $params['fechaInicio'] ?? null;
        $fechaFin = $params['fechaFin'] ?? null;

        $pedidos = Pedidos::whereRaw(
            "idPuntoVenta = ? and DATE_FORMAT(created_at, '%Y-%m-%d') BETWEEN ? AND ?",
            [
                $idPuntoVenta,
                date('Y-m-d', strtotime($fechaInicio)),
                date('Y-m-d', strtotime($fechaFin))
            ]
        )
        ->orderBy('created_at', 'desc')
        ->get()->load('detalles')->load('puntoventas');

        $pedidosIds = $pedidos->pluck('id')->all();
        $detalles = PedidosDetalles::whereIn('idPedido', $pedidosIds)->get();
        $productos = Productos::where([['idPuntoVenta', $idPuntoVenta], ['status', 1]])->get();
        $usuarios = User::select('users.*')->join('tbl_puntoventa_user', 'users.id', '=', 'tbl_puntoventa_user.idUser')->where([['tbl_puntoventa_user.idPuntoVenta', $idPuntoVenta], ['users.status', 1]])->get();
        $puntosVenta = PuntoVenta::get();

        $data = array(
            'pedidos' => $pedidos,
            'detalles' => $detalles,
            'productos' => $productos,
            'usuarios' => $usuarios,
            'puntoVentas' => $puntosVenta,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function reportePedido($id){
        $pedidos = Pedidos::find($id)->load('puntoventas')->load('detalles');
        $puntoVenta = PuntoVenta::find($pedidos->idPuntoVenta);
        $ubigeo = Ubigeo::find($puntoVenta->idUbigeo);
        $cajas = Cajas::where('idPuntoVenta', $pedidos->idPuntoVenta)->first();
        $pdf = PDF::loadView('pedidos', compact('pedidos', 'puntoVenta', 'ubigeo', 'cajas'));

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
        return $pdf->download('PEDIDO-' . str_pad($pedidos->id,6,"0",STR_PAD_LEFT) . '.pdf');
        // return $pdf->stream();
    }
}
