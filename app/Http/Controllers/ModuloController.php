<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ModuloController extends Controller
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

        $modulos = Modulo::orderBy('orden', 'asc')->get();

        $data = array(
            'modulos' => $modulos,
            'total' => @count($modulos),
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

        if ($modulos = Modulo::find($id)) {

            $data = array(
				'modulos' => $modulos,
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
            'nombre' => 'bail|required|string|max:255|unique:tbl_modulo'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        $orden = Modulo::max('orden');

        $modulos = new Modulo();
        $modulos->nombre = $params['nombre'] ?? null;
        $modulos->icono = $params['icono'] ?? null;
        $modulos->orden = $orden + 1;

        $modulos->save();

        $data = array(
            'modulos' => $modulos,
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

        if($modulos = Modulo::find($id)){
            $modulos->nombre = $params['nombre'] ?? $modulos->nombre;
            $modulos->icono = $params['icono'] ?? $modulos->icono;
            $modulos->save();

            $data = array(
                'modulos' => $modulos,
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

        if($modulos = Modulo::find($id)){
            $modulos->delete();

            $data = array(
                'modulos' => $modulos,
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

    public function cambiarOrden($id, Request $request){

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

        if($modulos = Modulo::find($id)){
            $params = $request->all();
            $validate = Validator::make($params, [
                'orden' => 'numeric',
            ]);

            if ($validate->fails()) {
                return response()->json($validate->errors(), 400);
            }

            $modulos->orden = $params['orden'] ?? $modulos->orden;
            $modulos->save();

            $data = array(
                'modulos' => $modulos,
                'message' => 'Orden actualizado correctamente',
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
