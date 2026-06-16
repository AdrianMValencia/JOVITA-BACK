<?php

namespace App\Http\Controllers;

use App\Models\OrdenRequerimiento;
use App\Models\Cajas;
use App\Models\OrdenRequerimientoDetalles;
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

class OrdenRequerimientoController extends Controller
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

        $ordenRequerimiento = OrdenRequerimiento::find($id)->orderBy('id', 'desc')->get()->load('puntoventas')->load('detalles');

        $data = array(
            'ordenRequerimiento' => $ordenRequerimiento,
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
            if ($ordenRequerimiento = OrdenRequerimiento::where('idPuntoVenta', $id)->get()) {

                $ordenRequerimiento = OrdenRequerimiento::where('idPuntoVenta', $id)->orderBy('id', 'desc')->get()->load('detalles')->load('puntoventas');
                $detalles = OrdenRequerimientoDetalles::get();

                $data = array(
                    'ordenRequerimiento' => $ordenRequerimiento,
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

            if ($ordenRequerimiento = OrdenRequerimiento::where([['idPuntoVenta', $id], ['status', 1], ['idUsuario', $user->id]])->get()) {

                $ordenRequerimiento = OrdenRequerimiento::where([['idPuntoVenta', $id], ['status', 1], ['idUsuario', $user->id]])->orderBy('id', 'desc')->get()->load('detalles')->load('puntoventas');
                $detalles = OrdenRequerimientoDetalles::get();

                $data = array(
                    'ordenRequerimiento' => $ordenRequerimiento,
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

        $ordenRequerimiento = new OrdenRequerimiento();
        $ordenRequerimiento->idPuntoVenta = $params['idPuntoVenta'] ?? null;
        $ordenRequerimiento->puntoventa = $params['puntoventa'] ?? null;
        $ordenRequerimiento->idPuntoVentaLlegada = $params['idPuntoVentaLlegada'] ?? null;
        $ordenRequerimiento->puntoVentaLlegada = $params['puntoVentaLlegada'] ?? null;
        $ordenRequerimiento->idUsuario = $params['idUsuario'] ?? null;
        $ordenRequerimiento->vendedor = $params['vendedor'] ?? null;
        $ordenRequerimiento->total = $params['total'] ?? null;
        $ordenRequerimiento->observaciones = $params['observaciones'] ?? null;
        $ordenRequerimiento->status = $params['status'] ?? null;
        $ordenRequerimiento->estadoActual = $params['estadoActual'] ?? null;
        $ordenRequerimiento->save();

        foreach ($params['detalles'] ?? [] as $value) {
            $ordenRequerimientoDetalles = new OrdenRequerimientoDetalles();
            $ordenRequerimientoDetalles->idOrdenRequerimiento = $ordenRequerimiento->id;
            $ordenRequerimientoDetalles->idProducto = $value['idProducto'] ?? null;
            $ordenRequerimientoDetalles->nombre = $value['nombre'] ?? null;
            $ordenRequerimientoDetalles->idCategoria = $value['idCategoria'] ?? null;
            $ordenRequerimientoDetalles->categoria = $value['categoria'] ?? null;
            $ordenRequerimientoDetalles->codigoBarra = $value['codigoBarra'] ?? null;
            $ordenRequerimientoDetalles->precioCompra = $value['precioCompra'] ?? null;
            $ordenRequerimientoDetalles->tipoPresentacion = $value['tipoPresentacion'] ?? null;
            $ordenRequerimientoDetalles->cantidadPaquetes = $value['cantidadPaquetes'] ?? null;
            $ordenRequerimientoDetalles->cantidad = $value['cantidad'] ?? null;
            $ordenRequerimientoDetalles->total = $value['total'] ?? null;
            $ordenRequerimientoDetalles->existencia = $value['existencia'] ?? null;
            $ordenRequerimientoDetalles->save();
        }

        $message = 'Se ha generado el Orden de Requerimiento N' . str_pad($ordenRequerimiento->id, 4, "0", STR_PAD_LEFT) . ' correctamente';
        $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');

        $data = array(
            'ordenRequerimiento' => $ordenRequerimiento,
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

        if($ordenRequerimiento = OrdenRequerimiento::find($id)){
            $ordenRequerimiento->idPuntoVentaLlegada = $params['idPuntoVentaLlegada'] ?? $ordenRequerimiento->idPuntoVentaLlegada;
            $ordenRequerimiento->puntoVentaLlegada = $params['puntoVentaLlegada'] ?? $ordenRequerimiento->puntoVentaLlegada;
            $ordenRequerimiento->idUsuario = $params['idUsuario'] ?? $ordenRequerimiento->idUsuario;
            $ordenRequerimiento->vendedor = $params['vendedor'] ?? $ordenRequerimiento->vendedor;
            $ordenRequerimiento->total = $params['total'] ?? $ordenRequerimiento->total;
            $ordenRequerimiento->observaciones = $params['observaciones'] ?? $ordenRequerimiento->observaciones;
            $ordenRequerimiento->estadoActual = $params['estadoActual'] ?? $ordenRequerimiento->estadoActual;
            $ordenRequerimiento->save();

            foreach ($params['detalles'] ?? [] as $value) {
                if (($value['id'] ?? 0) == 0) {
                    $ordenRequerimientoDetalles = new OrdenRequerimientoDetalles();
                }else{
                    $ordenRequerimientoDetalles = OrdenRequerimientoDetalles::find($value['id']);
                    if (!$ordenRequerimientoDetalles) {
                        $ordenRequerimientoDetalles = new OrdenRequerimientoDetalles();
                    }
                }
                $ordenRequerimientoDetalles->idOrdenRequerimiento = $ordenRequerimiento->id;
                $ordenRequerimientoDetalles->idProducto = $value['idProducto'] ?? null;
                $ordenRequerimientoDetalles->nombre = $value['nombre'] ?? null;
                $ordenRequerimientoDetalles->idCategoria = $value['idCategoria'] ?? null;
                $ordenRequerimientoDetalles->categoria = $value['categoria'] ?? null;
                $ordenRequerimientoDetalles->codigoBarra = $value['codigoBarra'] ?? null;
                $ordenRequerimientoDetalles->precioCompra = $value['precioCompra'] ?? null;
                $ordenRequerimientoDetalles->tipoPresentacion = $value['tipoPresentacion'] ?? null;
                $ordenRequerimientoDetalles->cantidadPaquetes = $value['cantidadPaquetes'] ?? null;
                $ordenRequerimientoDetalles->cantidad = $value['cantidad'] ?? null;
                $ordenRequerimientoDetalles->total = $value['total'] ?? null;
                $ordenRequerimientoDetalles->existencia = $value['existencia'] ?? null;
                $ordenRequerimientoDetalles->save();
            }

            $message = 'Se ha modificado el Orden de Requerimiento N' . str_pad($ordenRequerimiento->id, 4, "0", STR_PAD_LEFT) . ' correctamente';
            $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');

            $data = array(
                'ordenRequerimiento' => $ordenRequerimiento,
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

        if($ordenRequerimiento = OrdenRequerimiento::find($id)){

            $ordenRequerimientoDetalles = OrdenRequerimientoDetalles::where('idOrdenRequerimiento', $id)->get();
            foreach ($ordenRequerimientoDetalles as $key => $value) {
                $value->delete();
            }

            $ordenRequerimiento->delete();

            $data = array(
                'ordenRequerimiento' => $ordenRequerimiento,
                'message' => 'Orden de Requerimiento eliminado correctamente.',
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

        $ordenRequerimiento = OrdenRequerimiento::where('idPuntoVenta', $params['idPuntoVenta'] ?? 0)
            ->whereDate('created_at', '>=', $params['fechaInicio'] ?? '1970-01-01')
            ->whereDate('created_at', '<=', $params['fechaFin'] ?? '2099-12-31')
            ->whereIn('estadoActual', $params['estadoActual'] ?? [])
            ->with(['detalles', 'puntoventas'])
            ->orderBy('created_at', 'desc')
            ->get();

        $detalles = OrdenRequerimientoDetalles::get();
        $productos = Productos::where([['idPuntoVenta', $params['idPuntoVenta'] ?? 0], ['status', 1]])->get();
        $usuarios = User::select('users.*')->join('tbl_puntoventa_user', 'users.id', '=', 'tbl_puntoventa_user.idUser')->where([['tbl_puntoventa_user.idPuntoVenta', $params['idPuntoVenta'] ?? 0], ['users.status', 1]])->get();
        $puntosVenta = PuntoVenta::get();

        $data = array(
            'ordenRequerimiento' => $ordenRequerimiento,
            'detalles' => $detalles,
            'productos' => $productos,
            'usuarios' => $usuarios,
            'puntoVentas' => $puntosVenta,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function reporteOrdenRequerimiento($id){
        $ordenRequerimiento = OrdenRequerimiento::find($id)->load('puntoventas')->load('detalles');
        $puntoVenta = PuntoVenta::find($ordenRequerimiento->idPuntoVenta);
        $ubigeo = Ubigeo::find($puntoVenta->idUbigeo);
        $cajas = Cajas::where('idPuntoVenta', $ordenRequerimiento->idPuntoVenta)->first();
        $pdf = PDF::loadView('ordenRequerimiento', compact('ordenRequerimiento', 'puntoVenta', 'ubigeo', 'cajas'));

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
        return $pdf->download('ORDEN-REQUERIMIENTO-' . str_pad($ordenRequerimiento->id,6,"0",STR_PAD_LEFT) . '.pdf');
        // return $pdf->stream();
    }

}
