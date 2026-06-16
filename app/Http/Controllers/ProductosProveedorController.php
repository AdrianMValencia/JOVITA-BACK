<?php

namespace App\Http\Controllers;

use App\Models\ProductosProveedor;
use App\Models\Proveedores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductosProveedorController extends Controller
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

        $detalles = ProductosProveedor::orderBy('created_at', 'desc')->get();
            $data = array(
                'detalles' => $detalles,
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

        $detalles = ProductosProveedor::where('idPuntoVenta', $id)->get();

        $data = array(
            'detalles' => $detalles,
            'status' => 200
        );

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

        $existe = ProductosProveedor::where([
            ['idPuntoVenta', $params['idPuntoVenta'] ?? null],
            ['idProducto', $params['idProducto'] ?? null],
            ['idProveedor', $params['idProveedor'] ?? null]
        ])->first();

        if ($existe == null) {
            $detalles = new ProductosProveedor();
            $detalles->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $detalles->puntoVenta = $params['puntoVenta'] ?? null;
            $detalles->idProveedor = $params['idProveedor'] ?? null;
            $detalles->razonsocial = $params['razonsocial'] ?? null;
            $detalles->numeroDoi = $params['numeroDoi'] ?? null;
            $detalles->idProducto = $params['idProducto'] ?? null;
            $detalles->nombre = $params['nombre'] ?? null;
            $detalles->codigoBarra = $params['codigoBarra'] ?? null;
            $detalles->stockActual = $params['stockActual'] ?? null;
            $detalles->precio = $params['precio'] ?? null;
            $detalles->precioCompra = $params['precioCompra'] ?? null;
            $detalles->save();

            $data = array(
                'detalles' => $detalles,
                'message' => 'Registro agregado correctamente',
                'code' => 200
            );
        }else{
            $data = array(
                'message' => 'Esta asignación ya existe',
                'code' => 400
            );
        }

        return response()->json($data, 200);
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

        if($detalles = ProductosProveedor::find($id)){
            $detalles->idProveedor = $params['idProveedor'] ?? $detalles->idProveedor;
            $detalles->razonsocial = $params['razonsocial'] ?? $detalles->razonsocial;
            $detalles->numeroDoi = $params['numeroDoi'] ?? $detalles->numeroDoi;
            $detalles->idProducto = $params['idProducto'] ?? $detalles->idProducto;
            $detalles->nombre = $params['nombre'] ?? $detalles->nombre;
            $detalles->codigoBarra = $params['codigoBarra'] ?? $detalles->codigoBarra;
            $detalles->stockActual = $params['stockActual'] ?? $detalles->stockActual;
            $detalles->precio = $params['precio'] ?? $detalles->precio;
            $detalles->precioCompra = $params['precioCompra'] ?? $detalles->precioCompra;
            $detalles->save();

            $data = array(
                'detalles' => $detalles,
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

        if($detalles = ProductosProveedor::find($id)){

            $detalles->delete();

            $data = array(
                'detalles' => $detalles,
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

    public function cargar($id, $idProducto, Request $request){

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

        $detalles = ProductosProveedor::where([['idPuntoVenta', $id], ['idProducto', $idProducto]])->get();

        $data = array(
            'detalles' => $detalles,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }
}
