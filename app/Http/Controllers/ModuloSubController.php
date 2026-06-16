<?php

namespace App\Http\Controllers;

use App\Models\ModuloSub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ModuloSubController extends Controller
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

        $moduloSub = ModuloSub::get()->load('modulos');

        $data = array(
            'moduloSub' => $moduloSub,
            'total' => @count($moduloSub),
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

        if ($moduloSub = ModuloSub::find($id)) {

            $moduloSub = ModuloSub::where('id', $id)->first()->load('modulos');

            $data = array(
				'moduloSub' => $moduloSub,
                'status' => 200
            );

        }else{
            $data = array(
                'message' => 'Codigo no encontrado',
                'status' => 400
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

        $validator = Validator::make($params, [
            'nombre' => 'bail|required|string|max:255|unique:tbl_modulo_sub'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        $moduloSub = new ModuloSub();
        $moduloSub->idModulo = $params['idModulo'] ?? null;
        $moduloSub->nombre = $params['nombre'] ?? null;
        $moduloSub->ruta = $params['ruta'] ?? null;
        $moduloSub->save();

        $data = array(
            'moduloSub' => $moduloSub,
            'message' => 'Registro agregado correctamente.',
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

        $validator = Validator::make($params, [
            'nombre' => 'required|string|max:255'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        if($moduloSub = ModuloSub::find($id)){
            $moduloSub->idModulo = $params['idModulo'] ?? $moduloSub->idModulo;
            $moduloSub->nombre = $params['nombre'] ?? $moduloSub->nombre;
            $moduloSub->ruta = $params['ruta'] ?? $moduloSub->ruta;
            $moduloSub->save();

            $data = array(
                'moduloSub' => $moduloSub,
                'message' => 'Registro actualizado correctamente.',
                'status' => 200
            );

        }else{

            $data = array(
                'message' => 'Codigo no encontrado.',
                'status' => 400
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

        if($moduloSub = ModuloSub::find($id)){
            $moduloSub->delete();

            $data = array(
                'moduloSub' => $moduloSub,
                'message' => 'Registro eliminado correctamente.',
                'status' => 200
            );

        }else{
            $data = array(
                'message' => 'Código no encontrado',
                'status' => 400
            );
        }

        return response()->json($data, $data['status']);
	}
}
