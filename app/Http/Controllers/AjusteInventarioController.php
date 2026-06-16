<?php

namespace App\Http\Controllers;

use App\Models\AjusteInventario;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
class AjusteInventarioController extends Controller
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

        $ajustes = AjusteInventario::get()->load('series');
            $data = array(
                'ajustes' => $ajustes,
                'total' => @count($ajustes),
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

        if ($ajustes = AjusteInventario::where('idPuntoVenta', $id)->with('puntoventa')->get()) {
            $data = array(
                'ajustes' => $ajustes,
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

            $ajustes = new AjusteInventario();
            $ajustes->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $ajustes->puntoVenta = $params['puntoVenta'] ?? null;
            $ajustes->codigo_barras = $params['codigo_barras'] ?? null;
            $ajustes->idProducto = $params['idProducto'] ?? null;
            $ajustes->nombreProducto = $params['nombreProducto'] ?? null;
            $ajustes->idCategoria = $params['idCategoria'] ?? null;
            $ajustes->categoria = $params['categoria'] ?? null;
            $ajustes->motivo = $params['motivo'] ?? null;
            $ajustes->cantidad = $params['cantidad'] ?? null;
            $ajustes->save();

            $producto = Productos::find($params['idProducto'] ?? null);
            if($producto) {
                if(($params['motivo'] ?? '') == 'Faltante'){
                    $producto->stockActual = $producto->stockActual - ($params['cantidad'] ?? 0);
                }else{
                    $producto->stockActual = $producto->stockActual + ($params['cantidad'] ?? 0);
                }
                $producto->save();
            }

            $data = array(
                'ajustes' => $ajustes,
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

            if($ajustes = AjusteInventario::find($id)){
                $ajustes->puntoVenta = $params['puntoVenta'] ?? $ajustes->puntoVenta;
                $ajustes->codigo_barras = $params['codigo_barras'] ?? $ajustes->codigo_barras;
                $ajustes->idProducto = $params['idProducto'] ?? $ajustes->idProducto;
                $ajustes->nombreProducto = $params['nombreProducto'] ?? $ajustes->nombreProducto;
                $ajustes->motivo = $params['motivo'] ?? $ajustes->motivo;
                $ajustes->cantidad = $params['cantidad'] ?? $ajustes->cantidad;
                $ajustes->idCategoria = $params['idCategoria'] ?? $ajustes->idCategoria;
                $ajustes->categoria = $params['categoria'] ?? $ajustes->categoria;

                unset($params['id']);
                unset($params['created_at']);

                $ajustes->save();

                $producto = Productos::find($params['idProducto'] ?? null);
                if($producto) {
                    if(($params['motivo'] ?? '') == 'Faltante'){
                        $producto->stockActual = $producto->stockActual - ($params['cantidad'] ?? 0);
                    }else{
                        $producto->stockActual = $producto->stockActual + ($params['cantidad'] ?? 0);
                    }
                    $producto->save();
                }

                $data = array(
                    'ajustes' => $ajustes,
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

        if($ajustes = AjusteInventario::find($id)){

            $ajustes->delete();

            $data = array(
                'ajustes' => $ajustes,
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
