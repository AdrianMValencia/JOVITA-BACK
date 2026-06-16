<?php

namespace App\Http\Controllers;

use App\Models\Proveedores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProveedoresController extends Controller
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

        $proveedores = Proveedores::get()->load('puntoventa')->load('tipodoi')->load('ubigeos');
            $data = array(
                'proveedores' => $proveedores,
                'total' => @count($proveedores),
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

        if ($proveedores = Proveedores::where('idPuntoVenta', $id)->get()) {
            $proveedores = Proveedores::where('idPuntoVenta', $id)->orderBy('razonSocial', 'asc')->get()->load('puntoventa')->load('tipodoi')->load('ubigeos');
            $data = array(
                'proveedores' => $proveedores,
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

    $proveedores = new Proveedores();
    $proveedores->idPuntoVenta = $params['idPuntoVenta'] ?? null;
    $proveedores->idTipoDoi = $params['idTipoDoi'] ?? null;
    $proveedores->numeroDoi = $params['numeroDoi'] ?? null;
    $proveedores->nombre = $params['nombre'] ?? null;
    $proveedores->razonsocial = $params['razonsocial'] ?? null;
    $proveedores->pais = $params['pais'] ?? null;
    $proveedores->idUbigeo = $params['idUbigeo'] ?? null;
    $proveedores->direccion = $params['direccion'] ?? null;
    $proveedores->correo = $params['correo'] ?? null;
    $proveedores->celular = $params['celular'] ?? null;
    $proveedores->telefono = $params['telefono'] ?? null;
    $proveedores->imagen = $params['imagen'] ?? null;
    $proveedores->observaciones = $params['observaciones'] ?? null;
    $proveedores->status = $params['status'] ?? null;
    $proveedores->save();

        $data = array(
            'proveedores' => $proveedores,
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

        if($proveedores = Proveedores::find($id)){
            $proveedores->idPuntoVenta = $params['idPuntoVenta'] ?? $proveedores->idPuntoVenta;
            $proveedores->idTipoDoi = $params['idTipoDoi'] ?? $proveedores->idTipoDoi;
            $proveedores->numeroDoi = $params['numeroDoi'] ?? $proveedores->numeroDoi;
            $proveedores->nombre = $params['nombre'] ?? $proveedores->nombre;
            $proveedores->razonsocial = $params['razonsocial'] ?? $proveedores->razonsocial;
            $proveedores->pais = $params['pais'] ?? $proveedores->pais;
            $proveedores->idUbigeo = $params['idUbigeo'] ?? $proveedores->idUbigeo;
            $proveedores->direccion = $params['direccion'] ?? $proveedores->direccion;
            $proveedores->correo = $params['correo'] ?? $proveedores->correo;
            $proveedores->celular = $params['celular'] ?? $proveedores->celular;
            $proveedores->telefono = $params['telefono'] ?? $proveedores->telefono;
            $proveedores->imagen = $params['imagen'] ?? $proveedores->imagen;
            $proveedores->observaciones = $params['observaciones'] ?? $proveedores->observaciones;
            $proveedores->status = $params['status'] ?? $proveedores->status;
            $proveedores->save();

            $data = array(
                'proveedores' => $proveedores,
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

        if($proveedores = Proveedores::find($id)){

            $proveedores->delete();

            $data = array(
                'proveedores' => $proveedores,
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
