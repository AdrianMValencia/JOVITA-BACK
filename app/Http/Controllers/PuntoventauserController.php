<?php

namespace App\Http\Controllers;

use App\Models\Puntoventauser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class PuntoventauserController extends Controller
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

        $puntoventauser = Puntoventauser::where('idUser', $user->id)->get()->load('puntoventa')->load('user');
            $data = array(
                'puntoventauser' => $puntoventauser,
                'total' => @count($puntoventauser),
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

        if ($puntoventauser = Puntoventauser::where('idUser', $id)->get()) {

            $puntoventauser = Puntoventauser::where('idUser', $id)->get()->load('puntoventa')->load('user');
            $data = array(
                'puntoventauser' => $puntoventauser,
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

        $puntoventauser = new Puntoventauser();
            $puntoventauser->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $puntoventauser->idUser = $params['idUser'] ?? null;
        $puntoventauser->save();

        $data = array(
            'puntoventauser' => $puntoventauser,
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

            if($puntoventauser = Puntoventauser::find($id)){
                $puntoventauser->idPuntoVenta = $params['idPuntoVenta'] ?? $puntoventauser->idPuntoVenta;
                $puntoventauser->idUser = $params['idUser'] ?? $puntoventauser->idUser;

                unset($params['id']);
                unset($params['created_at']);

                $puntoventauser->save();

                $data = array(
                    'puntoventauser' => $puntoventauser,
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
}
