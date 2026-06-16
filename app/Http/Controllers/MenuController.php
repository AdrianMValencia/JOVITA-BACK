<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class MenuController extends Controller
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

        $menu = Menu::orderBy('orden', 'desc')->get()->load('submenu');
            $data = array(
                'menu' => $menu,
                'total' => @count($menu),
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

        if ($menu = Menu::find($id)) {

            $data = array(
                'menu' => $menu,
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

    $orden = Menu::max('orden');

    $menu = new Menu();
    $menu->titulo_es = $params['titulo_es'] ?? null;
    $menu->titulo_en = $params['titulo_en'] ?? null;
    $menu->titulo_fr = $params['titulo_fr'] ?? null;
    $menu->titulo_pr = $params['titulo_pr'] ?? null;
    $menu->enlace = $params['enlace'] ?? null;
    $menu->tenlace = $params['tenlace'] ?? null;
    $menu->nventana = $params['nventana'] ?? null;
    $menu->padre = $params['padre'] ?? null;
    $menu->estado = $params['estado'] ?? null;
    $menu->orden = $orden + 1;
    $menu->save();

        $data = array(
            'menu' => $menu,
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

        if($menu = Menu::find($id)){
            $menu->titulo_es = $params['titulo_es'] ?? $menu->titulo_es;
            $menu->titulo_en = $params['titulo_en'] ?? $menu->titulo_en;
            $menu->titulo_fr = $params['titulo_fr'] ?? $menu->titulo_fr;
            $menu->titulo_pr = $params['titulo_pr'] ?? $menu->titulo_pr;
            $menu->enlace = $params['enlace'] ?? $menu->enlace;
            $menu->tenlace = $params['tenlace'] ?? $menu->tenlace;
            $menu->nventana = $params['nventana'] ?? $menu->nventana;
            $menu->padre = $params['padre'] ?? $menu->padre;
            $menu->save();

            $data = array(
                'menu' => $menu,
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

        if($menu = Menu::find($id)){

            $menu->delete();

            $data = array(
                'menu' => $menu,
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

        $params = $request->all();

        $validate = Validator::make($params, [
            'orden' => 'numeric',
        ]);

        if ($validate->fails()) {
            return response()->json($validate->errors(), 400);
        }

        if($menu = Menu::find($id)){
            $menu->orden = $params['orden'] ?? $menu->orden;
            $menu->save();

            $data = array(
                'menu' => $menu,
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

        $params = $request->all();

        if($menu = Menu::find($id)){
            $menu->estado = $params['estado'] ?? $menu->estado;
            $menu->save();

            $data = array(
                'menu' => $menu,
                'message' => 'Estado actualizado correctamente',
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
