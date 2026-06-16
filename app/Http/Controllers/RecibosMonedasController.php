<?php

namespace App\Http\Controllers;

use App\Models\RecibosMonedas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;


class RecibosMonedasController extends Controller
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

        $recibosMonedas = RecibosMonedas::get()->load('puntoventa')->load('user');
            $data = array(
                'recibosMonedas' => $recibosMonedas,
                'total' => @count($recibosMonedas),
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

        if ($recibosMonedas = RecibosMonedas::find($id)) {

            $data = array(
                'recibosMonedas' => $recibosMonedas,
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

            $recibosMonedas = new RecibosMonedas();
            $recibosMonedas->idRecibo = $params['idRecibo'] ?? null;
            $recibosMonedas->idMoneda = $params['idMoneda'] ?? null;
            $recibosMonedas->tipoCambio = $params['tipoCambio'] ?? null;
            $recibosMonedas->save();

            $data = array(
                'recibosMonedas' => $recibosMonedas,
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

            if($recibosMonedas = RecibosMonedas::find($id)){
                $recibosMonedas->idRecibo = $params['idRecibo'] ?? $recibosMonedas->idRecibo;
                $recibosMonedas->idMoneda = $params['idMoneda'] ?? $recibosMonedas->idMoneda;
                $recibosMonedas->tipoCambio = $params['tipoCambio'] ?? $recibosMonedas->tipoCambio;

                unset($params['id']);
                unset($params['created_at']);

                $recibosMonedas->save();

                $data = array(
                    'recibosMonedas' => $recibosMonedas,
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

        if($recibosMonedas = RecibosMonedas::find($id)){

            $recibosMonedas->delete();

            $data = array(
                'recibosMonedas' => $recibosMonedas,
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
