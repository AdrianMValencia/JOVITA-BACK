<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RolesModuloOperacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class RolesModuloOperacionController extends Controller
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

        $usuarios = User::whereNotIn('users.id', [$user->id, 1])->get()->load('roles');
        $permisos = RolesModuloOperacion::get();

        $data = array(
            'usuarios' => $usuarios,
            'permisos' => $permisos,
            'total' => @count($permisos),
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

        if ($permisos = RolesModuloOperacion::find($id)) {

            $permisos = RolesModuloOperacion::where('id', $id)->first();

            $data = array(
				'permisos' => $permisos,
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
            $params_array = $request->all();

            if (isset($params_array[0]['idUsuario'])) {
                $idUsuario = $params_array[0]['idUsuario'];
            } elseif (isset($params_array[0]->idUsuario)) {
                $idUsuario = $params_array[0]->idUsuario;
            } else {
                $idUsuario = null;
            }

            if($idUsuario){
                $delete = RolesModuloOperacion::where([['idUsuario', $idUsuario]]);
                $delete->delete();
            }

            foreach ($params_array as $variable) {
                $permisos = new RolesModuloOperacion();
                if (is_array($variable)) {
                    $permisos->idUsuario = $variable['idUsuario'] ?? null;
                    $permisos->idSubModulo = $variable['idSubModulo'] ?? null;
                    $permisos->completed = $variable['completed'] ?? null;
                } else {
                    $permisos->idUsuario = $variable->idUsuario ?? null;
                    $permisos->idSubModulo = $variable->idSubModulo ?? null;
                    $permisos->completed = $variable->completed ?? null;
                }
                $permisos->status = 1;
                $permisos->save();
            }

            $data = array(
                'permisos' => $permisos,
                'message' => 'Permisos guardados correctamente.',
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
            $params_array = $request->all();

            foreach ($params_array as $variable) {
                $permisos = new RolesModuloOperacion();
                if (is_array($variable)) {
                    $permisos->idUsuario = $variable['idUsuario'] ?? null;
                    $permisos->idSubModulo = $variable['idSubModulo'] ?? null;
                    $permisos->completed = $variable['completed'] ?? null;
                } else {
                    $permisos->idUsuario = $variable->idUsuario ?? null;
                    $permisos->idSubModulo = $variable->idSubModulo ?? null;
                    $permisos->completed = $variable->completed ?? null;
                }
                $permisos->status = 1;
                $permisos->save();
            }

            $data = array(
                'permisos' => $permisos,
                'message' => 'Permisos modificados correctamente.',
                'status' => 200
            );

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

        if($permisos = RolesModuloOperacion::find($id)){
            $permisos->delete();

            $data = array(
                'permisos' => $permisos,
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
