<?php

namespace App\Http\Controllers;

use App\Models\ProductosFaltantes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductosFaltantesController extends Controller
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

        $productosFaltantes = ProductosFaltantes::get()->load('series');
            $data = array(
                'productosFaltantes' => $productosFaltantes,
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

        if ($productosFaltantes = ProductosFaltantes::where('idPuntoVenta', $id)->orderBy('fecha', 'desc')->get()) {

            $data = array(
                'productosFaltantes' => $productosFaltantes,
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

    $productosFaltantes = new ProductosFaltantes();
    $productosFaltantes->idPuntoVenta = $params['idPuntoVenta'] ?? null;
    $productosFaltantes->puntoVenta = $params['puntoVenta'] ?? null;
    $productosFaltantes->idUsuario = $user->id;
    $productosFaltantes->usuario = $params['usuario'] ?? null;
    $productosFaltantes->fecha = $params['fecha'] ?? null;
    $productosFaltantes->idProducto = $params['idProducto'] ?? null;
    $productosFaltantes->codigo = $params['codigo'] ?? null;
    $productosFaltantes->producto = $params['producto'] ?? null;
    $productosFaltantes->precioVenta = $params['precioVenta'] ?? null;
    $productosFaltantes->cantidad = $params['cantidad'] ?? null;
    $productosFaltantes->idCategoria = $params['idCategoria'] ?? null;
    $productosFaltantes->categoria = $params['categoria'] ?? null;
    $productosFaltantes->total = $params['total'] ?? null;
    $productosFaltantes->save();

        $data = array(
            'productosFaltantes' => $productosFaltantes,
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

        if($productosFaltantes = ProductosFaltantes::find($id)){
            $productosFaltantes->fecha = $params['fecha'] ?? $productosFaltantes->fecha;
            $productosFaltantes->idProducto = $params['idProducto'] ?? $productosFaltantes->idProducto;
            $productosFaltantes->codigo = $params['codigo'] ?? $productosFaltantes->codigo;
            $productosFaltantes->producto = $params['producto'] ?? $productosFaltantes->producto;
            $productosFaltantes->precioVenta = $params['precioVenta'] ?? $productosFaltantes->precioVenta;
            $productosFaltantes->cantidad = $params['cantidad'] ?? $productosFaltantes->cantidad;
            $productosFaltantes->idCategoria = $params['idCategoria'] ?? $productosFaltantes->idCategoria;
            $productosFaltantes->categoria = $params['categoria'] ?? $productosFaltantes->categoria;
            $productosFaltantes->total = $params['total'] ?? $productosFaltantes->total;
            $productosFaltantes->save();

            $data = array(
                'productosFaltantes' => $productosFaltantes,
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

        if($productosFaltantes = ProductosFaltantes::find($id)){

            $productosFaltantes->delete();

            $data = array(
                'productosFaltantes' => $productosFaltantes,
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

    public function obtenerProductosFaltantesEditar($id, Request $request){

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

        if ($productosFaltantes = ProductosFaltantes::find($id)) {

            $data = array(
                'productosFaltantes' => $productosFaltantes,
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
