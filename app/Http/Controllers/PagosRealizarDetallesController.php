<?php

namespace App\Http\Controllers;

use App\Models\PagosRealizarDetalle;
use App\Models\PagosRealizar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class PagosRealizarDetallesController extends Controller
{
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

        if ($pagosDetalles = PagosRealizarDetalle::where('idPagoRealizar', $id)->orderBy('created_at', 'desc')->get()->load('pagos')) {

            $pagosRealizar = PagosRealizar::find($id);
            $data = array(
                'pagosRealizar' => $pagosRealizar,
                'pagosDetalles' => $pagosDetalles,
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

    $pagosDetalles = new PagosRealizarDetalle();
    $pagosDetalles->idPagoRealizar = $params['idPagoRealizar'] ?? null;
    $pagosDetalles->fechaVencimiento = $params['fechaVencimiento'] ?? null;
    $pagosDetalles->cantidad = $params['cantidad'] ?? null;
    $pagosDetalles->monto = $params['monto'] ?? null;
    $pagosDetalles->interes = $params['interes'] ?? null;
    $pagosDetalles->total = $params['total'] ?? null;
    $pagosDetalles->status = $params['status'] ?? null;
    $pagosDetalles->idUsuario = $params['idUsuario'] ?? null;
    $pagosDetalles->idModalidad = $params['idModalidad'] ?? null;
    $pagosDetalles->save();

        $data = array(
            'pagosDetalles' => $pagosDetalles,
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

        if($pagosDetalles = PagosRealizarDetalle::find($id)){
            $pagosDetalles->idPagoRealizar = $params['idPagoRealizar'] ?? $pagosDetalles->idPagoRealizar;
            $pagosDetalles->fechaVencimiento = $params['fechaVencimiento'] ?? $pagosDetalles->fechaVencimiento;
            $pagosDetalles->cantidad = $params['cantidad'] ?? $pagosDetalles->cantidad;
            $pagosDetalles->monto = $params['monto'] ?? $pagosDetalles->monto;
            $pagosDetalles->interes = $params['interes'] ?? $pagosDetalles->interes;
            $pagosDetalles->total = $params['total'] ?? $pagosDetalles->total;
            $pagosDetalles->status = $params['status'] ?? $pagosDetalles->status;
            $pagosDetalles->idUsuario = $params['idUsuario'] ?? $pagosDetalles->idUsuario;
            $pagosDetalles->idModalidad = $params['idModalidad'] ?? $pagosDetalles->idModalidad;
            $pagosDetalles->save();

            $data = array(
                'pagosDetalles' => $pagosDetalles,
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

        if($pagosDetalles = PagosRealizarDetalle::find($id)){

            $pagosDetalles->delete();

            $data = array(
                'pagosDetalles' => $pagosDetalles,
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
