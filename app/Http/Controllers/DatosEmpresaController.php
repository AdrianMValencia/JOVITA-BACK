<?php

namespace App\Http\Controllers;

use App\Models\DatosEmpresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class DatosEmpresaController extends Controller
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

        $datosEmpresa = DatosEmpresa::orderBy('created_at', 'desc')->get();
            $data = array(
                'datosEmpresa' => $datosEmpresa,
                'total' => @count($datosEmpresa),
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

        if ($datosEmpresa = DatosEmpresa::find($id)) {

            $data = array(
                'datosEmpresa' => $datosEmpresa,
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

    $datosEmpresa = new DatosEmpresa();
    $datosEmpresa->ruc = $params['ruc'] ?? null;
    $datosEmpresa->nombreLegal = $params['nombreLegal'] ?? null;
    $datosEmpresa->nombreComercial = $params['nombreComercial'] ?? null;
    $datosEmpresa->logo = $params['logo'] ?? null;
    $datosEmpresa->telefonos = $params['telefonos'] ?? null;
    $datosEmpresa->correoEmpresa = $params['correoEmpresa'] ?? null;
    $datosEmpresa->direccion = $params['direccion'] ?? null;
    $datosEmpresa->pagina = $params['pagina'] ?? null;
    $datosEmpresa->cuentasBancarias = $params['cuentasBancarias'] ?? null;
    $datosEmpresa->nombreBanco = $params['nombreBanco'] ?? null;
    $datosEmpresa->codigoInterbancario = $params['codigoInterbancario'] ?? null;
    $datosEmpresa->save();

        $data = array(
            'datosEmpresa' => $datosEmpresa,
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

        if($datosEmpresa = DatosEmpresa::find($id)){
            $datosEmpresa->ruc = $params['ruc'] ?? $datosEmpresa->ruc;
            $datosEmpresa->nombreLegal = $params['nombreLegal'] ?? $datosEmpresa->nombreLegal;
            $datosEmpresa->nombreComercial = $params['nombreComercial'] ?? $datosEmpresa->nombreComercial;
            $datosEmpresa->logo = $params['logo'] ?? $datosEmpresa->logo;
            $datosEmpresa->telefonos = $params['telefonos'] ?? $datosEmpresa->telefonos;
            $datosEmpresa->correoEmpresa = $params['correoEmpresa'] ?? $datosEmpresa->correoEmpresa;
            $datosEmpresa->direccion = $params['direccion'] ?? $datosEmpresa->direccion;
            $datosEmpresa->pagina = $params['pagina'] ?? $datosEmpresa->pagina;
            $datosEmpresa->cuentasBancarias = $params['cuentasBancarias'] ?? $datosEmpresa->cuentasBancarias;
            $datosEmpresa->nombreBanco = $params['nombreBanco'] ?? $datosEmpresa->nombreBanco;
            $datosEmpresa->codigoInterbancario = $params['codigoInterbancario'] ?? $datosEmpresa->codigoInterbancario;

            $datosEmpresa->save();

            $data = array(
                'datosEmpresa' => $datosEmpresa,
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

        if($datosEmpresa = DatosEmpresa::find($id)){

            $datosEmpresa->delete();

            $data = array(
                'datosEmpresa' => $datosEmpresa,
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

    /**
     * Obtener datos de la empresa sin autenticación
     * Endpoint público para mostrar información básica
     */
    public function datosEmpresaPublicos(){
        $datosEmpresa = DatosEmpresa::orderBy('created_at', 'desc')->first();

        if($datosEmpresa){
            $data = array(
                'datosEmpresa' => $datosEmpresa,
                'status' => 200
            );
        } else {
            $data = array(
                'message' => 'No se encontraron datos de la empresa',
                'status' => 404
            );
        }

        return response()->json($data, $data['status']);
    }
}
