<?php

namespace App\Http\Controllers;

use App\Models\Clientes;
use App\Models\TipoDoi;
use App\Models\Ubigeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use GuzzleHttp\Client;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ClientesController extends Controller
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

        $clientes = Clientes::orderBy('created_at', 'desc')->get()->load('tipodoi')->load('ubigeos')->load('puntoventa');
            $data = array(
                'clientes' => $clientes,
                'total' => @count($clientes),
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

        if ($clientes = Clientes::where('idPuntoVenta', $id)->get()) {
            $clientes = Clientes::where('idPuntoVenta', $id)->get()->load('tipodoi')->load('ubigeos')->load('puntoventa');
            $data = array(
                'clientes' => $clientes,
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

    $clientes = new Clientes();
    $clientes->idPuntoVenta = $params['idPuntoVenta'] ?? null;
    $clientes->idTipoDoi = $params['idTipoDoi'] ?? null;
    $clientes->numeroDoi = $params['numeroDoi'] ?? null;
    $clientes->nombre = $params['nombre'] ?? null;
    $clientes->direccion = $params['direccion'] ?? null;
    $clientes->idUbigeo = $params['idUbigeo'] ?? null;
    $clientes->pais = $params['pais'] ?? null;
    $clientes->correo = $params['correo'] ?? null;
    $clientes->celular = $params['celular'] ?? null;
    $clientes->telefono = $params['telefono'] ?? null;
    $clientes->imagen = $params['imagen'] ?? null;
    $clientes->observaciones = $params['observaciones'] ?? null;
    $clientes->status = $params['status'] ?? null;
    $clientes->save();

        $data = array(
            'clientes' => $clientes,
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

        if($clientes = Clientes::find($id)){
            $clientes->idPuntoVenta = $params['idPuntoVenta'] ?? $clientes->idPuntoVenta;
            $clientes->idTipoDoi = $params['idTipoDoi'] ?? $clientes->idTipoDoi;
            $clientes->numeroDoi = $params['numeroDoi'] ?? $clientes->numeroDoi;
            $clientes->nombre = $params['nombre'] ?? $clientes->nombre;
            $clientes->direccion = $params['direccion'] ?? $clientes->direccion;
            $clientes->idUbigeo = $params['idUbigeo'] ?? $clientes->idUbigeo;
            $clientes->pais = $params['pais'] ?? $clientes->pais;
            $clientes->correo = $params['correo'] ?? $clientes->correo;
            $clientes->celular = $params['celular'] ?? $clientes->celular;
            $clientes->telefono = $params['telefono'] ?? $clientes->telefono;
            $clientes->imagen = $params['imagen'] ?? $clientes->imagen;
            $clientes->observaciones = $params['observaciones'] ?? $clientes->observaciones;
            $clientes->status = $params['status'] ?? $clientes->status;

            unset($params['id']);
            unset($params['created_at']);

            $clientes->save();

            $data = array(
                'clientes' => $clientes,
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

        if($clientes = Clientes::find($id)){

            $clientes->delete();

            $data = array(
                'clientes' => $clientes,
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
                        'idUbigeo' => 1249,
                        'codigo' => '150101',
                        'ubigeo' => 'LIMA-LIMA-LIMA',
                        'status' => 200
                    );
                }else{

                    $ubigeo = Ubigeo::where('codigo', $response['ubigeo'])->first();
                    $data = array(
                        'nombre' => $response['nombre'],
                        'tipoDocumento' => $response['tipoDocumento'],
                        'numeroDocumento' => $response['numeroDocumento'],
                        'direccion' => $response['direccion'],
                        'idUbigeo' => $ubigeo->id,
                        'codigo' => $ubigeo->codigo,
                        'ubigeo' => $ubigeo->ubigeo,
                        'pais' => $response['distrito'],
                        'status' => 200
                    );
                }


            }else{

                $data = array(
                    'nombre' => $response['nombre'],
                    'tipoDocumento' => $response['tipoDocumento'],
                    'numeroDocumento' => $response['numeroDocumento'],
                    'idUbigeo' => 1249,
                    'codigo' => '150101',
                    'ubigeo' => 'LIMA-LIMA-LIMA',
                    'status' => 200
                );
            }
       }

        return response()->json($data, $data['status']);
    }

    public function buscarClientes($documento, $idPuntoVenta, Request $request){

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

        if ($clientes = Clientes::where('numeroDoi', $documento)->first()) {

            $clientes = Clientes::where('numeroDoi', $documento)->first()->load('tipodoi')->load('ubigeos')->load('puntoventa');

            $data = array(
				'clientes' => $clientes,
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

                $tipoCliente = TipoDoi::where('codigo', $params->tipoDocumento)->first();

                $clientes = new Clientes();
                $clientes->idPuntoVenta = $idPuntoVenta;
                $clientes->idTipoDoi = $tipoCliente->id;
                $clientes->numeroDoi = $params->numeroDocumento;
                $clientes->nombre = $params->nombre;
                $clientes->direccion = (isset($params->direccion) == true) ? $params->direccion : '';
                $clientes->idUbigeo = (isset($params->idUbigeo) == true) ? $params->idUbigeo : 1250;
                $clientes->codigo = (isset($params->codigo) == true) ? $params->codigo : 1250;
                $clientes->ubigeo = (isset($params->ubigeo) == true) ? $params->ubigeo : 1250;
                $clientes->pais = (isset($params->pais) == true) ? $params->pais : '';
                $clientes->correo = '';
                $clientes->celular = '';
                $clientes->telefono = '';
                $clientes->imagen = '';
                $clientes->observaciones = '';
                $clientes->status = 1;
                // $clientes->save();

                $data = array(
                    'clientes' => $clientes,
                    'status' => 200
                );
            }

        }

        return response()->json($data, $data['status']);
    }

    /**
     * Simple search by ruc query string parameter.
     */
    public function buscar(Request $request)
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $ruc = $request->query('ruc');
        if (empty($ruc)) {
            return response()->json(['message' => 'ruc parameter missing'], 400);
        }

        $clientes = Clientes::where('numeroDoi', $ruc)->get()->load('tipodoi')->load('ubigeos')->load('puntoventa');
        return response()->json(['clientes' => $clientes, 'status' => 200], 200);
    }
}

