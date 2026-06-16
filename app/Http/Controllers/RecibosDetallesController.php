<?php

namespace App\Http\Controllers;

use App\Models\RecibosDetalles;
use App\Models\Productos;
use App\Models\Recibos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class RecibosDetallesController extends Controller
{
    public function index(){

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

        $recibosDetalles = RecibosDetalles::get()->load('recibos')->load('productos');
            $data = array(
                'recibosDetalles' => $recibosDetalles,
                'total' => @count($recibosDetalles),
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

        if ($recibosDetalles = RecibosDetalles::find($id)) {

            $data = array(
                'recibosDetalles' => $recibosDetalles,
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
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }
            $params = $request->all();

            $recibosDetalles = new RecibosDetalles();
            $recibosDetalles->idRecibo = $params['idRecibo'] ?? null;
            $recibosDetalles->idProducto = $params['idProducto'] ?? null;
            $recibosDetalles->nombre = $params['nombre'] ?? null;
            $recibosDetalles->detalle = $params['detalle'] ?? null;
            $recibosDetalles->precio = $params['precio'] ?? null;
            $recibosDetalles->cantidad = $params['cantidad'] ?? null;
            $recibosDetalles->subtotal = $params['subtotal'] ?? null;
            $recibosDetalles->igv = $params['igv'] ?? null;
            $recibosDetalles->total = $params['total'] ?? null;
            $recibosDetalles->porcentajeDesc = $params['porcentajeDesc'] ?? null;
            $recibosDetalles->totalDesc = $params['totalDesc'] ?? null;
            $recibosDetalles->save();

            $data = array(
                'recibosDetalles' => $recibosDetalles,
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
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }
            $params = $request->all();

            if($recibosDetalles = RecibosDetalles::find($id)){
                $recibosDetalles->idRecibo = $params['idRecibo'] ?? $recibosDetalles->idRecibo;
                $recibosDetalles->idProducto = $params['idProducto'] ?? $recibosDetalles->idProducto;
                $recibosDetalles->nombre = $params['nombre'] ?? $recibosDetalles->nombre;
                $recibosDetalles->detalle = $params['detalle'] ?? $recibosDetalles->detalle;
                $recibosDetalles->precio = $params['precio'] ?? $recibosDetalles->precio;
                $recibosDetalles->cantidad = $params['cantidad'] ?? $recibosDetalles->cantidad;
                $recibosDetalles->subtotal = $params['subtotal'] ?? $recibosDetalles->subtotal;
                $recibosDetalles->igv = $params['igv'] ?? $recibosDetalles->igv;
                $recibosDetalles->total = $params['total'] ?? $recibosDetalles->total;
                $recibosDetalles->porcentajeDesc = $params['porcentajeDesc'] ?? $recibosDetalles->porcentajeDesc;
                $recibosDetalles->totalDesc = $params['totalDesc'] ?? $recibosDetalles->totalDesc;

                unset($params['id']);
                unset($params['created_at']);

                $recibosDetalles->save();

                $data = array(
                    'recibosDetalles' => $recibosDetalles,
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
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        if($recibosDetalles = RecibosDetalles::find($id)){

            $productos = Productos::find($recibosDetalles->idProducto);
            $productos->stockActual = $productos->stockActual + $recibosDetalles->cantidad;
            $productos->save();

            $recibos = Recibos::find($recibosDetalles->idRecibo);
            $recibos->total = $recibos->total - $recibosDetalles->total;
            $recibos->save();

            $recibosDetalles->delete();

            $data = array(
                'recibosDetalles' => $recibosDetalles,
                'message' => 'Producto eliminado correctamente.',
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
