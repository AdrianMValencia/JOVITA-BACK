<?php

namespace App\Http\Controllers;

use App\Models\Bancos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use GuzzleHttp\Client;

class BancosController extends Controller
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

        $bancos = Bancos::orderBy('created_at', 'desc')->get();
            $data = array(
                'bancos' => $bancos,
                'total' => @count($bancos),
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

        if ($bancos = Bancos::where('idPuntoVenta', $id)->get()) {
            $data = array(
                'bancos' => $bancos,
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

            $bancos = new Bancos();
            $bancos->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $bancos->ruc = $params['ruc'] ?? null;
            $bancos->nombre = $params['nombre'] ?? null;
            $bancos->siglas = $params['siglas'] ?? null;
            $bancos->funcionario = $params['funcionario'] ?? null;
            $bancos->telefono = $params['telefono'] ?? null;
            $bancos->celular = $params['celular'] ?? null;
            $bancos->correo = $params['correo'] ?? null;
            $bancos->observaciones = $params['observaciones'] ?? null;
            $bancos->status = $params['status'] ?? null;
            $bancos->save();

            $data = array(
                'bancos' => $bancos,
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

            if($bancos = Bancos::find($id)){
                $bancos->idPuntoVenta = $params['idPuntoVenta'] ?? $bancos->idPuntoVenta;
                $bancos->ruc = $params['ruc'] ?? $bancos->ruc;
                $bancos->nombre = $params['nombre'] ?? $bancos->nombre;
                $bancos->siglas = $params['siglas'] ?? $bancos->siglas;
                $bancos->funcionario = $params['funcionario'] ?? $bancos->funcionario;
                $bancos->telefono = $params['telefono'] ?? $bancos->telefono;
                $bancos->celular = $params['celular'] ?? $bancos->celular;
                $bancos->correo = $params['correo'] ?? $bancos->correo;
                $bancos->observaciones = $params['observaciones'] ?? $bancos->observaciones;
                $bancos->status = $params['status'] ?? $bancos->status;

                unset($params['id']);
                unset($params['created_at']);

                $bancos->save();

                $data = array(
                    'bancos' => $bancos,
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

        if($bancos = Bancos::find($id)){

            $bancos->delete();

            $data = array(
                'bancos' => $bancos,
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

    public function consultasSUNAT($tipo, $numero){

        $token = '';

        $client = new Client(['base_uri' => 'https://api.apis.net.pe', 'verify' => false]);

        $parameters = [
            'http_errors' => false,
            'connect_timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Referer' => 'https://apis.net.pe/api-consulta-ruc',
                'User-Agent' => 'laravel/guzzle',
                'Accept' => 'application/json',
            ],
            'query' => ['numero' => $numero]
        ];
        $res = $client->request('GET', '/v1/'. $tipo, $parameters);
        $response = json_decode($res->getBody()->getContents(), true);

        if(isset($response['error'])){

            $data = array(
                'message' => $response['error'],
                'status' => 404
            );

        }else{
            if($response['tipoDocumento'] == '6'){

                if(substr($response['numeroDocumento'], 0, 2) == '10'){

                    $data = array(
                        'nombre' => $response['nombre'],
                        'tipoDocumento' => $response['tipoDocumento'],
                        'numeroDocumento' => $response['numeroDocumento'],
                        'status' => 200
                    );
                }else{

                    $data = array(
                        'nombre' => $response['nombre'],
                        'tipoDocumento' => $response['tipoDocumento'],
                        'numeroDocumento' => $response['numeroDocumento'],
                        'direccion' => $response['direccion'],
                        'pais' => $response['distrito'],
                        'status' => 200
                    );
                }


            }else{

                $data = array(
                    'nombre' => $response['nombre'],
                    'tipoDocumento' => $response['tipoDocumento'],
                    'numeroDocumento' => $response['numeroDocumento'],
                    'status' => 200
                );
            }
       }

        return response()->json($data, $data['status']);
    }

    public function buscarClientes($documento, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['Usuario no encontrado'], 404);
            }
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        if ($bancos = Bancos::where('ruc', $documento)->first()) {

            $bancos = Bancos::where('ruc', $documento)->first();

            $data = array(
				'bancos' => $bancos,
                'status' => 200
            );

        }else{

            $retVal = ((substr($documento, 0, 2) == "20" || substr($documento, 0, 2) == "10"))? 'ruc' : 'dni' ;
            $json = $this->consultasSUNAT($retVal, $documento);
            $params = json_decode(json_encode($json))->original;

            if($params->status == 404){
                $data = array(
                    'message' => 'Código no encontrado',
                    'status' => 201
                );
            }else{

                $bancos = new Bancos();
                $bancos->ruc = $params->numeroDocumento;
                $bancos->nombre = $params->nombre;
                $bancos->direccion = (isset($params->direccion) == true) ? $params->direccion : '';
                $bancos->correo = '';
                $bancos->celular = '';
                $bancos->telefono = '';
                $bancos->correo = '';
                $bancos->observaciones = '';
                $bancos->status = 1;
                // $bancos->save();

                $data = array(
                    'bancos' => $bancos,
                    'status' => 200
                );
            }

        }

        return response()->json($data, $data['status']);
    }
}
