<?php

namespace App\Http\Controllers;

use App\Models\ProductoAjustes;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductoAjustesController extends Controller
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

        $productoAjustes = ProductoAjustes::orderBy('created_at', 'desc')->get();
            $data = array(
                'productoAjustes' => $productoAjustes,
                'total' => @count($productoAjustes),
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

        if ($productoAjustes = ProductoAjustes::where('idPuntoVenta', $id)->get()->load('productos')->load('puntoventa')) {

            $data = array(
                'productoAjustes' => $productoAjustes,
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

        $productoAjustes = new ProductoAjustes();
        $productoAjustes->idPuntoVenta = $params['idPuntoVenta'] ?? null;
        $productoAjustes->idProducto = $params['idProducto'] ?? null;
        $productoAjustes->nombre = $params['nombre'] ?? null;
        $productoAjustes->stock = $params['stock'] ?? null;
        $productoAjustes->stockAjuste = $params['stockAjuste'] ?? null;
        $productoAjustes->cantidadAjuste = $params['cantidadAjuste'] ?? null;
        $productoAjustes->tipoAjuste = $params['tipoAjuste'] ?? null;
        $productoAjustes->observaciones = $params['observaciones'] ?? null;
        $productoAjustes->status = $params['status'] ?? null;
        $productoAjustes->save();

        $productos = Productos::find($params['idProducto'] ?? null);
        if ($productos) {
            $productos->stockActual = $params['stockAjuste'] ?? $productos->stockActual;
            $productos->save();
        }

        $data = array(
            'productoAjustes' => $productoAjustes,
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

        if($productoAjustes = ProductoAjustes::find($id)){
            $productoAjustes->idPuntoVenta = $params['idPuntoVenta'] ?? $productoAjustes->idPuntoVenta;
            $productoAjustes->idProducto = $params['idProducto'] ?? $productoAjustes->idProducto;
            $productoAjustes->nombre = $params['nombre'] ?? $productoAjustes->nombre;
            $productoAjustes->stock = $params['stock'] ?? $productoAjustes->stock;
            $productoAjustes->stockAjuste = $params['stockAjuste'] ?? $productoAjustes->stockAjuste;
            $productoAjustes->cantidadAjuste = $params['cantidadAjuste'] ?? $productoAjustes->cantidadAjuste;
            $productoAjustes->tipoAjuste = $params['tipoAjuste'] ?? $productoAjustes->tipoAjuste;
            $productoAjustes->observaciones = $params['observaciones'] ?? $productoAjustes->observaciones;
            $productoAjustes->status = $params['status'] ?? $productoAjustes->status;

            $productos = Productos::find($params['idProducto'] ?? $productoAjustes->idProducto);
            if ($productos) {
                $productos->stockActual = $params['stockAjuste'] ?? $productos->stockActual;
                $productos->save();
            }

            $productoAjustes->save();

            $data = array(
                'productoAjustes' => $productoAjustes,
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

        if($productoAjustes = ProductoAjustes::find($id)){

            $productoAjustes->delete();

            $data = array(
                'productoAjustes' => $productoAjustes,
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
