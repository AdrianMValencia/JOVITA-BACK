<?php

namespace App\Http\Controllers;

use App\Models\ComprasAjustes;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Twilio\Rest\Client;

class ComprasAjustesController extends Controller
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

        $comprasAjustes = ComprasAjustes::orderBy('created_at', 'desc')->get();
            $data = array(
                'comprasAjustes' => $comprasAjustes,
                'total' => @count($comprasAjustes),
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

        if ($comprasAjustes = ComprasAjustes::where('idPuntoVenta', $id)->get()->load('productos')->load('puntoventa')) {

            $data = array(
                'comprasAjustes' => $comprasAjustes,
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

        $comprasAjustes = new ComprasAjustes();
        $comprasAjustes->idCompra = $params['idCompra'] ?? null;
        $comprasAjustes->idPuntoVenta = $params['idPuntoVenta'] ?? null;
        $comprasAjustes->idProducto = $params['idProducto'] ?? null;
        $comprasAjustes->nombre = $params['nombre'] ?? null;
        $comprasAjustes->stock = $params['stock'] ?? null;
        $comprasAjustes->stockAjuste = $params['stockAjuste'] ?? null;
        $comprasAjustes->cantidadAjuste = $params['cantidadAjuste'] ?? null;
        $comprasAjustes->tipoAjuste = $params['tipoAjuste'] ?? null;
        $comprasAjustes->observaciones = $params['observaciones'] ?? null;
        $comprasAjustes->status = $params['status'] ?? null;
        $comprasAjustes->save();

        $productos = Productos::find($params['idProducto'] ?? null);
        if ($productos) {
            $productos->stockActual = $params['stockAjuste'] ?? $productos->stockActual;
            $productos->save();
        }

        // $client->messages->create(
        //     env( 'NUMERO1' ),
        //     [
        //         'from' => env( 'TWILIO_FROM' ),
        //         'body' => "Se ha editado el stock del producto ". $productos->nombre .", era ". $productos->stockActual ." ahora es ". $params->stockAjuste .", lo hizo el usuario ". $user->nombre ."",
        //     ]
        // );

        // $client->messages->create(
        //     env( 'NUMERO2' ),
        //     [
        //         'from' => env( 'TWILIO_FROM' ),
        //         'body' => "Se ha editado el stock del producto ". $productos->nombre .", era ". $productos->stockActual ." ahora es ". $params->stockAjuste .", lo hizo el usuario ". $user->nombre ."",
        //     ]
        // );

        $data = array(
            'comprasAjustes' => $comprasAjustes,
            'message' => 'Registro agregado correctamente',
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

        if($comprasAjustes = ComprasAjustes::find($id)){
            $comprasAjustes->idCompra = $params['idCompra'] ?? $comprasAjustes->idCompra;
            $comprasAjustes->idPuntoVenta = $params['idPuntoVenta'] ?? $comprasAjustes->idPuntoVenta;
            $comprasAjustes->idProducto = $params['idProducto'] ?? $comprasAjustes->idProducto;
            $comprasAjustes->nombre = $params['nombre'] ?? $comprasAjustes->nombre;
            $comprasAjustes->stock = $params['stock'] ?? $comprasAjustes->stock;
            $comprasAjustes->stockAjuste = $params['stockAjuste'] ?? $comprasAjustes->stockAjuste;
            $comprasAjustes->cantidadAjuste = $params['cantidadAjuste'] ?? $comprasAjustes->cantidadAjuste;
            $comprasAjustes->tipoAjuste = $params['tipoAjuste'] ?? $comprasAjustes->tipoAjuste;
            $comprasAjustes->observaciones = $params['observaciones'] ?? $comprasAjustes->observaciones;
            $comprasAjustes->status = $params['status'] ?? $comprasAjustes->status;

            $productos = Productos::find($params['idProducto']);
            $productos->stockActual = $params['stockAjuste'];
            $productos->save();

            unset($params['id']);
            unset($params['created_at']);

            $comprasAjustes->save();

            $data = array(
                'comprasAjustes' => $comprasAjustes,
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

        if($comprasAjustes = ComprasAjustes::find($id)){

            $comprasAjustes->delete();

            $data = array(
                'comprasAjustes' => $comprasAjustes,
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
}
