<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AlmacenController extends Controller
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

        $almacenes = Almacen::orderBy('created_at', 'desc')->get();
            $data = array(
                'almacenes' => $almacenes,
                'total' => @count($almacenes),
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

        if ($almacenes = Almacen::where('idPuntoVenta', $id)->get()->load('ubigeos')->load('puntoventa')) {

            $data = array(
                'almacenes' => $almacenes,
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

            $almacenes = new Almacen();
            $almacenes->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $almacenes->nombre = $params['nombre'] ?? null;
            $almacenes->direccion = $params['direccion'] ?? null;
            $almacenes->idUbigeo = $params['idUbigeo'] ?? null;
            $almacenes->observaciones = $params['observaciones'] ?? null;
            $almacenes->status = $params['status'] ?? null;
            $almacenes->save();

            $data = array(
                'almacenes' => $almacenes,
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

            if($almacenes = Almacen::find($id)){
                $almacenes->idPuntoVenta = $params['idPuntoVenta'] ?? $almacenes->idPuntoVenta;
                $almacenes->nombre = $params['nombre'] ?? $almacenes->nombre;
                $almacenes->direccion = $params['direccion'] ?? $almacenes->direccion;
                $almacenes->idUbigeo = $params['idUbigeo'] ?? $almacenes->idUbigeo;
                $almacenes->observaciones = $params['observaciones'] ?? $almacenes->observaciones;
                $almacenes->status = $params['status'] ?? $almacenes->status;

                unset($params['id']);
                unset($params['created_at']);

                $almacenes->save();

                $data = array(
                    'almacenes' => $almacenes,
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

        if($almacenes = Almacen::find($id)){

            $almacenes->delete();

            $data = array(
                'almacenes' => $almacenes,
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
