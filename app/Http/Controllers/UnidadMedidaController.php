<?php

namespace App\Http\Controllers;

use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class UnidadMedidaController extends Controller
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

        $unidadMedidas = UnidadMedida::orderBy('created_at', 'desc')->get();
            $data = array(
                'unidadMedidas' => $unidadMedidas,
                'total' => @count($unidadMedidas),
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

        if ($unidadMedidas = UnidadMedida::where('idPuntoVenta', $id)->get()) {

            $data = array(
                'unidadMedidas' => $unidadMedidas,
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

            $unidadMedidas = new UnidadMedida();
            $unidadMedidas->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $unidadMedidas->nombre = $params['nombre'] ?? null;
            $unidadMedidas->abreviatura = $params['abreviatura'] ?? null;
            $unidadMedidas->observaciones = $params['observaciones'] ?? null;
            $unidadMedidas->status = $params['status'] ?? null;
            $unidadMedidas->save();

            $data = array(
                'unidadMedidas' => $unidadMedidas,
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

            if($unidadMedidas = UnidadMedida::find($id)){
                $unidadMedidas->idPuntoVenta = $params['idPuntoVenta'] ?? $unidadMedidas->idPuntoVenta;
                $unidadMedidas->nombre = $params['nombre'] ?? $unidadMedidas->nombre;
                $unidadMedidas->abreviatura = $params['abreviatura'] ?? $unidadMedidas->abreviatura;
                $unidadMedidas->observaciones = $params['observaciones'] ?? $unidadMedidas->observaciones;
                $unidadMedidas->status = $params['status'] ?? $unidadMedidas->status;

                unset($params['id']);
                unset($params['created_at']);

                $unidadMedidas->save();

                $data = array(
                    'unidadMedidas' => $unidadMedidas,
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

        if($unidadMedidas = UnidadMedida::find($id)){

            $unidadMedidas->delete();

            $data = array(
                'unidadMedidas' => $unidadMedidas,
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
