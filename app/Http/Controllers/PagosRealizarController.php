<?php

namespace App\Http\Controllers;

use App\Models\PagosRealizar;
use App\Models\PagosRealizarDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class PagosRealizarController extends Controller
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

        $pagosRealizar = PagosRealizar::orderBy('created_at', 'desc')->get()->load('bancos')->load('monedas')->load('periodicidades')->load('tipos')->load('detalles');
            $data = array(
                'pagosRealizar' => $pagosRealizar,
                'total' => @count($pagosRealizar),
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

        if ($pagosRealizar = PagosRealizar::where('idPuntoVenta', $id)->orderBy('created_at', 'desc')->get()->load('bancos')->load('monedas')->load('periodicidades')->load('tipos')->load('detalles')) {

            $data = array(
                'pagosRealizar' => $pagosRealizar,
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

    $pagosRealizar = new PagosRealizar();
    $pagosRealizar->idPuntoVenta = $params['idPuntoVenta'] ?? null;
    $pagosRealizar->nombre = $params['nombre'] ?? null;
    $pagosRealizar->periodicidad = $params['periodicidad'] ?? null;
    $pagosRealizar->tipo = $params['tipo'] ?? null;
    $pagosRealizar->idBanco = $params['idBanco'] ?? null;
    $pagosRealizar->idMoneda = $params['idMoneda'] ?? null;
    $pagosRealizar->cantidad = $params['cantidad'] ?? null;
    $pagosRealizar->monto = $params['monto'] ?? null;
    $pagosRealizar->observaciones = $params['observaciones'] ?? null;
    $pagosRealizar->status = $params['status'] ?? null;
    $pagosRealizar->save();

        $data = array(
            'pagosRealizar' => $pagosRealizar,
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

        if($pagosRealizar = PagosRealizar::find($id)){
            $pagosRealizar->idPuntoVenta = $params['idPuntoVenta'] ?? $pagosRealizar->idPuntoVenta;
            $pagosRealizar->nombre = $params['nombre'] ?? $pagosRealizar->nombre;
            $pagosRealizar->periodicidad = $params['periodicidad'] ?? $pagosRealizar->periodicidad;
            $pagosRealizar->tipo = $params['tipo'] ?? $pagosRealizar->tipo;
            $pagosRealizar->idBanco = $params['idBanco'] ?? $pagosRealizar->idBanco;
            $pagosRealizar->idMoneda = $params['idMoneda'] ?? $pagosRealizar->idMoneda;
            $pagosRealizar->cantidad = $params['cantidad'] ?? $pagosRealizar->cantidad;
            $pagosRealizar->monto = $params['monto'] ?? $pagosRealizar->monto;
            $pagosRealizar->observaciones = $params['observaciones'] ?? $pagosRealizar->observaciones;
            $pagosRealizar->status = $params['status'] ?? $pagosRealizar->status;
            $pagosRealizar->save();

            $data = array(
                'pagosRealizar' => $pagosRealizar,
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

        if($pagosRealizar = PagosRealizar::find($id)){

            $pagosRealizar->delete();

            $data = array(
                'pagosRealizar' => $pagosRealizar,
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
