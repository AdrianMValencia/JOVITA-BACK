<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Http\Requests;
use App\Models\Menu;
use App\Models\SubMenu;

class SubMenuController extends Controller
{
    public function obtener($id, Request $request){

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

        if ($submenus = SubMenu::where('menuId', $id)) {

            $submenus = SubMenu::where('menuId', $id)
                                ->orderBy('orden', 'ASC')
                                ->get()
                                ->load('menu');

            $data = array(
                'submenus' => $submenus,
                'total' => @count($submenus),
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


        if ($submenus = SubMenu::find($id)) {

            $submenus = SubMenu::find($id);

            $data = array(
                'submenus' => $submenus,
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

            $orden = SubMenu::where('menuId', $params['menuId'] ?? null)->max('orden');
            $ruta = Menu::where('id', $params['menuId'] ?? null)->first();

            $submenus = new SubMenu();
            $submenus->menuId = $params['menuId'] ?? null;
            $submenus->titulo_es = $params['titulo_es'] ?? null;
            $submenus->titulo_en = $params['titulo_en'] ?? null;
            $submenus->titulo_fr = $params['titulo_fr'] ?? null;
            $submenus->titulo_pr = $params['titulo_pr'] ?? null;
            $submenus->enlace = $params['enlace'] ?? null;
            $submenus->tenlace = $params['tenlace'] ?? null;
            $submenus->nventana = $params['nventana'] ?? null;
            $submenus->orden = ($orden ?? 0) + 1;
            $submenus->estado = 1;

            $submenus->save();

            $data = array(
                'submenus' => $submenus,
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

            if($submenus = SubMenu::find($id)){
                $submenus->titulo_es = $params['titulo_es'] ?? $submenus->titulo_es;
                $submenus->titulo_en = $params['titulo_en'] ?? $submenus->titulo_en;
                $submenus->titulo_fr = $params['titulo_fr'] ?? $submenus->titulo_fr;
                $submenus->titulo_pr = $params['titulo_pr'] ?? $submenus->titulo_pr;
                $submenus->enlace = $params['enlace'] ?? $submenus->enlace;
                $submenus->tenlace = $params['tenlace'] ?? $submenus->tenlace;
                $submenus->nventana = $params['nventana'] ?? $submenus->nventana;

                unset($params['id']);
                unset($params['menuId']);
                unset($params['created_at']);
                unset($params['menu']);

                $submenus->save();

                $data = array(
                    'submenus' => $submenus,
                    'message' => 'SubMenú modificado correctamente',
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

        if($submenus = SubMenu::find($id)){

            $submenus = SubMenu::find($id);
            $submenus->delete();

            $data = array(
                'submenus' => $submenus,
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

    function eliminar_tildes($cadena){

        //Codificamos la cadena en formato utf8 en caso de que nos de errores
        // $cadena = utf8_decode($cadena);

        //Ahora reemplazamos las letras
        $cadena = str_replace(
            array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'),
            array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'),
            $cadena
        );

        $cadena = str_replace(
            array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'),
            array('e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'),
            $cadena );

        $cadena = str_replace(
            array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'),
            array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'),
            $cadena );

        $cadena = str_replace(
            array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'),
            array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'),
            $cadena );

        $cadena = str_replace(
            array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'),
            array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'),
            $cadena );

        $cadena = str_replace(
            array('ñ', 'Ñ', 'ç', 'Ç'),
            array('n', 'N', 'c', 'C'),
            $cadena
        );

        return $cadena;
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
            if($submenus = SubMenu::find($id)){
                $params = $request->all();

                $validate = Validator::make($params, [
                    'orden' => 'numeric',
                ]);

                if ($validate->fails()) {
                    return response()->json($validate->errors(), 400);
                }

                $submenus->orden = $params['orden'] ?? $submenus->orden;
                $submenus->save();

                $data = array(
                    'submenus' => $submenus,
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
            if($submenus = SubMenu::find($id)){
                $params = $request->all();
                $submenus->estado = $params['estado'] ?? $submenus->estado;
                $submenus->save();

                $data = array(
                    'submenus' => $submenus,
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

    public function obtenerFrontend($id, Request $request){

        if ($submenus = SubMenu::where('menuId', $id)) {

            $submenus = SubMenu::where('menuId', $id)
                                ->orderBy('orden', 'ASC')
                                ->get();

            $data = array(
                'submenus' => $submenus,
                'total' => @count($submenus),
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
