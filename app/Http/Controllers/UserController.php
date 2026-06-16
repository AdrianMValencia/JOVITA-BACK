<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Roles;
use App\Models\RolesModuloOperacion;
use App\Models\Modulo;
use App\Models\ModuloSub;
use App\Models\Puntoventauser;
use App\Models\PuntoVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UserController extends Controller
{
    public function authenticate(Request $request){
    $credentials = $request->all();

        try {

            if (! $token = JWTAuth::attempt($credentials)) {
                // return response()->json(['error' => 'datos incorrectos'], 400);

                $data = array(
                    'error' => 'Datos incorrectos',
                    'status' => 400
                );

            } else {

                $usuario = auth()->user();

                if($usuario->status != "1"){
                    return response()->json(['Usuario inactivo, comuniquese con el administrador'], 404);
                }

                $menu = Modulo::orderBy('orden', 'asc')->get();
                $subMenu = ModuloSub::orderBy('idModulo', 'ASC')->orderBy('orden', 'ASC')->get();
                $permisos = RolesModuloOperacion::where('idUsuario', $usuario->id)->get();

                if($usuario->idRol != 1){
                    $puntoVenta = PuntoVenta::select('tbl_punto_venta.*')->join('tbl_puntoventa_user', 'tbl_punto_venta.id', '=', 'tbl_puntoventa_user.idPuntoVenta')
                    ->where('tbl_puntoventa_user.idUser', $usuario->id)->get();
                    // $puntoVenta = Puntoventauser::where('idUser', $usuario->id)->get()->load('puntoventa')->load('user');
                }else{
                    $puntoVenta = PuntoVenta::get();
                }

                $data = array(
                    'token' => $token,
                    'menu' => $menu,
                    'subMenu' => $subMenu,
                    'permisos' => $permisos,
                    'usuario' => $usuario,
                    'puntoVenta' => $puntoVenta,
                    'status' => 200
                );

            }

        } catch (JWTException $e) {
            // return response()->json(['error' => 'no se pudo crear el token'], 500);
            $data = array(
                'error' => 'No se pudo crear el token',
                'status' => 500
            );
        }

        // return response()->json(compact('token'));
        return response()->json($data, $data['status']);
    }

    public function getAuthenticatedUser(){
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }

            if($user->estado != "1"){
                return response()->json(['Usuario inactivo, comuniquese con el administrador'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        // compact('user')
        $usuario = auth()->user();
        $menu = Modulo::orderBy('orden')->get();
        $subMenu = ModuloSub::orderBy('nombre')->get();
        $permisos = RolesModuloOperacion::where('idRol', $usuario->idRol)->get();

        $data = array(
            'menu' => $menu,
            'subMenu' => $subMenu,
            'permisos' => $permisos,
            'usuario' => $usuario,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function register(Request $request){
    $params_array = $request->all();
    $params = (object)$params_array;

        $validator = Validator::make($params_array, [
            'usuario' => 'required|string|max:255|unique:users',
            'password' => 'required|string|min:6',
            // password_confirmation
        ]);

        if($validator->fails()){
                return response()->json($validator->errors()->toJson(), 400);
        }

        $user = User::create([
            'idRol' => 1,
            'usuario' => $params->usuario,
            'password' => Hash::make($params->password),
            'nombre' => $params->nombre,
            'status' => true,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json(compact('user','token'), 201);
    }

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

        $data = array(
            'usuarios' => $usuarios,
            'total' => @count($usuarios),
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

        if ($asignacion = Puntoventauser::where('idPuntoVenta', $id)->get()) {
            $usuarios = User::select('users.*')->join('tbl_puntoventa_user', 'users.id', '=', 'tbl_puntoventa_user.idUser')->where('tbl_puntoventa_user.idPuntoVenta', $id)->get()->load('roles');
            $data = array(
				'usuarios' => $usuarios,
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
        $params = (object)$params_array;

        $validator = Validator::make($params_array, [
            'usuario' => 'bail|required|string|max:255|unique:users',
            'password' => 'bail|required|string|min:6|confirmed'
        ]);

        if($validator->fails()){
                return response()->json($validator->errors()->toJson(), 400);
        }

        $users = new User();
        $users->idRol = $params->idRol;
        $users->nombre = $params->nombre;
        $users->usuario = $params->usuario;
        $users->password = Hash::make($params->password);
        $users->direccion = $params->direccion;
        $users->email = $params->email;
        $users->telefono = $params->telefono;
        $users->celular = $params->celular;
        $users->ciudad = $params->ciudad;
        $users->imagen = $params->imagen;
        $users->status = 1;
        $users->save();

        $puntoventauser = new Puntoventauser();
        $puntoventauser->idPuntoVenta = $params->idPuntoVenta;
        $puntoventauser->idUser = $users->id;
        $puntoventauser->save();

        $data = array(
            'usuarios' => $users,
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

        $params_array = $request->all();
        $params = (object)$params_array;

        $validator = Validator::make($params_array, [
            'usuario' => 'required|string|max:255'
        ]);

        if($validator->fails()){
                return response()->json($validator->errors()->toJson(), 400);
        }

        if($users = User::find($id)){

            $users->idRol = $params->idRol;
            $users->nombre = $params->nombre;
            $users->usuario = $params->usuario;
            $users->password = Hash::make($params->password);
            $users->direccion = $params->direccion;
            $users->email = $params->email;
            $users->telefono = $params->telefono;
            $users->celular = $params->celular;
            $users->ciudad = $params->ciudad;
            $users->imagen = $params->imagen;
            $users->status = $params->status;

            if ($params->password != '') {
                $users->password = Hash::make($params->password);
            }

            unset($params_array['id']);
            unset($params_array['created_at']);
            $users->save();

            $data = array(
                'usuarios' => $users,
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

        if($usuarios = User::find($id)){

            $usuarios->delete();

            $data = array(
                'usuarios' => $usuarios,
                'message' => 'Registro eliminado correctamente.',
                'status' => 200
            );

        }else{
            $data = array(
                'message' => 'Usuario no encontrado',
                'status' => 400
            );
        }

        return response()->json($data, $data['status']);
	}

	public function cambiarEstado($id, Request $request){

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
        $params = (object)$params_array;

        if($user = User::find($id)){
            $user->status = $params->status;
            $user->save();
            $data = array(
                'usuario' => $user,
                'status' => 200,
                'message' => 'Estado actualizado correctamente'
            );
        }else{
            $data = array(
                'message' => 'Usuario no encontrado',
                'status' => 404
            );
        }
        return response()->json($data, $data['status']);
    }

    public function perfil($id, Request $request){

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
        $params = (object)$params_array;

        $validate = Validator::make($params_array, [
            'usuario' => 'required|min:3|unique:users',
            'nombre' => 'required|min:3'
        ]);

        if ($validate->fails()){
            return response()->json([
                        'status'=>422,
                        'message'=>$validate->errors()
                    ], 422);
        }

        if($users = User::find($id)){
            $users->nombre = $params->nombre;
            $users->nivel = $params->nivel;

            if ($params->password != "") {
                if ($users->password != $params->password) {
                    $pwd = Hash::make($params->password);
                    $users->password = $pwd;
                }
            }

            if ($users->idRol == $params->idRol) {
                $users->idRol = $params->idRol;
                $users->save();
                $data = array(
                    'usuarios' => $params,
                    'message' => 'Datos actualizados correctamente.',
                    'status' => 200
                );
            }else{
                $data = array(
                    'message' => 'No es administrador.',
                    'status' => 400
                );
            }
        }else{
            $data = array(
                'message' => 'Codigo no encontrado.',
                'status' => 400
            );
        }
    	return response()->json($data, $data['status']);
	}

    public function roleAdmin($id, Request $request){

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
        $params = (object)$params_array;

        if($users = User::find($id)){
            $users->idRol = $params->idRol;
            unset($params_array['id']);
            unset($params_array['created_at']);
            $users->save();
            $data = array(
                'usuarios' => $params,
                'status' => 'success',
                'message' => 'Rol actualizado correctamente.',
                'code' => 200
            );
        }else{
            $data = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Codigo no encontrado.'
            );
        }
        return response()->json($data, 200);
    }

    public function cargarPermisos($idUsuario, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['Usuario no encontrado'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $params_array = $request->all();
        $params = (object)$params_array;

        if(User::find($idUsuario)){
            $modulos = Modulo::orderBy('orden')->get()->load('subModulos');
            $permisos = RolesModuloOperacion::get()->load('subModulos');
            $data = array(
                'modulos' => $modulos,
                'permisos' => $permisos,
                'status' => 200,
                'message' => 'Estado actualizado correctamente'
            );
        }else{
            $data = array(
                'message' => 'Usuario no encontrado',
                'status' => 404
            );
        }
        return response()->json($data, $data['status']);
    }

    public function cargarUserPermisos(){
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['Usuario no encontrado'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $usuarios = User::get()->load('roles');

        $data = array(
            'usuarios' => $usuarios,
            'total' => @count($usuarios),
            'status' => 200
        );

        return response()->json($data, $data['status']);
	}

    public function cargarRoles(Request $request){
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

        $roles = Roles::get();

        $data = array(
            'roles' => $roles,
            'total' => @count($roles),
            'status' => 200
        );

        return response()->json($data, $data['status']);
	}

    public function cambiarPassword($id, Request $request){

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
        $params = (object)$params_array;

        if($users = User::find($id)){
            $users->password = Hash::make($params->password);
            unset($params_array['id']);
            unset($params_array['created_at']);
            $users->save();
            $data = array(
                'usuarios' => $params,
                'status' => 'success',
                'message' => 'Contraseña actualizada correctamente.',
                'code' => 200
            );
        }else{
            $data = array(
                'status' => 'error',
                'code' => 400,
                'message' => 'Codigo no encontrado.'
            );
        }
    	return response()->json($data, 200);
	}

    public function cargarUsuarios(){
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

        $usuarios = User::where('status', 1)->get()->load('roles');

        $data = array(
            'usuarios' => $usuarios,
            'total' => @count($usuarios),
            'status' => 200
        );

        return response()->json($data, $data['status']);
	}
}
