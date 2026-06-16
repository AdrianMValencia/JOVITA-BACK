<?php

namespace App\Http\Controllers;

use App\Models\TiposCambio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use GuzzleHttp\Client;

class TiposCambioController extends Controller
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

        $tipoCambio = TiposCambio::orderBy('created_at', 'desc')->get();
            $data = array(
                'tipoCambio' => $tipoCambio,
                'total' => @count($tipoCambio),
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

        if ($tipoCambio = TiposCambio::where('idPuntoVenta', $id)->get()->load('monedas')) {

            $data = array(
                'tipoCambio' => $tipoCambio,
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

            $tipoCambio = new TiposCambio();
            $tipoCambio->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $tipoCambio->idMoneda = $params['idMoneda'] ?? null;
            $tipoCambio->fecha = $params['fecha'] ?? null;
            $response = $this->tipoCambioSUNAT($tipoCambio->fecha);
            $tipoCambio->valorCompra = $response['compra'] ?? null;
            $tipoCambio->valorVenta = $response['venta'] ?? null;
            $tipoCambio->observaciones = $params['observaciones'] ?? null;
            $tipoCambio->status = $params['status'] ?? null;
            $tipoCambio->save();

            $data = array(
                'tipoCambio' => $tipoCambio,
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

            if($tipoCambio = TiposCambio::find($id)){
                $tipoCambio->idPuntoVenta = $params['idPuntoVenta'] ?? $tipoCambio->idPuntoVenta;
                $tipoCambio->idMoneda = $params['idMoneda'] ?? $tipoCambio->idMoneda;
                $tipoCambio->fecha = $params['fecha'] ?? $tipoCambio->fecha;
                $tipoCambio->valorCompra = $params['valorCompra'] ?? $tipoCambio->valorCompra;
                $tipoCambio->valorVenta = $params['valorVenta'] ?? $tipoCambio->valorVenta;
                $tipoCambio->observaciones = $params['observaciones'] ?? $tipoCambio->observaciones;
                $tipoCambio->status = $params['status'] ?? $tipoCambio->status;

                unset($params['id']);
                unset($params['created_at']);

                $tipoCambio->save();

                $data = array(
                    'tipoCambio' => $tipoCambio,
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

        if($tipoCambio = TiposCambio::find($id)){

            $tipoCambio->delete();

            $data = array(
                'tipoCambio' => $tipoCambio,
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

    public function tipoCambioSUNAT($fechaCambio){
        $token = '';
        $client = new Client(['base_uri' => 'https://api.apis.net.pe', 'verify' => false]);
        $parameters = [
            'http_errors' => false,
            'connect_timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Referer' => 'https://apis.net.pe/api-sunat-tipo-de-cambio',
                'User-Agent' => 'laravel/guzzle',
                'Accept' => 'application/json',
            ],
            'query' => ['fecha' => $fechaCambio] //'2021-06-27'
        ];
        $res = $client->request('GET', '/v1/tipo-cambio-sunat', $parameters);
        $response = json_decode($res->getBody()->getContents(), true);
        return $response;
    }
}
