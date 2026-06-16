<?php

namespace App\Http\Controllers;

use App\Models\Ubicaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class UbicacionesController extends Controller
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

        $ubicaciones = Ubicaciones::orderBy('created_at', 'desc')->get();
            $data = array(
                'ubicaciones' => $ubicaciones,
                'total' => @count($ubicaciones),
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

        if ($ubicaciones = Ubicaciones::where('idPuntoVenta', $id)->get()->load('productos')->load('almacenes')->load('puntoventa')) {

            $data = array(
                'ubicaciones' => $ubicaciones,
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

            $ubicaciones = new Ubicaciones();
            $ubicaciones->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $ubicaciones->idProducto = $params['idProducto'] ?? null;
            $ubicaciones->nombre = $params['nombre'] ?? null;
            $ubicaciones->idAlmacen = $params['idAlmacen'] ?? null;
            $ubicaciones->nombreAlmacen = $params['nombreAlmacen'] ?? null;
            $ubicaciones->ubicacion1 = $params['ubicacion1'] ?? null;
            $ubicaciones->anaquel1 = $params['anaquel1'] ?? null;
            $ubicaciones->gaveta1 = $params['gaveta1'] ?? null;
            $ubicaciones->numeroGaveta1 = $params['numeroGaveta1'] ?? null;
            $ubicaciones->ubicacion2 = $params['ubicacion2'] ?? null;
            $ubicaciones->anaquel2 = $params['anaquel2'] ?? null;
            $ubicaciones->gaveta2 = $params['gaveta2'] ?? null;
            $ubicaciones->numeroGaveta2 = $params['numeroGaveta2'] ?? null;
            $ubicaciones->observaciones = $params['observaciones'] ?? null;
            $ubicaciones->status = $params['status'] ?? null;
            $ubicaciones->save();

            $data = array(
                'ubicaciones' => $ubicaciones,
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

            if($ubicaciones = Ubicaciones::find($id)){
                $ubicaciones->idPuntoVenta = $params['idPuntoVenta'] ?? $ubicaciones->idPuntoVenta;
                $ubicaciones->idProducto = $params['idProducto'] ?? $ubicaciones->idProducto;
                $ubicaciones->nombre = $params['nombre'] ?? $ubicaciones->nombre;
                $ubicaciones->idAlmacen = $params['idAlmacen'] ?? $ubicaciones->idAlmacen;
                $ubicaciones->nombreAlmacen = $params['nombreAlmacen'] ?? $ubicaciones->nombreAlmacen;
                $ubicaciones->ubicacion1 = $params['ubicacion1'] ?? $ubicaciones->ubicacion1;
                $ubicaciones->anaquel1 = $params['anaquel1'] ?? $ubicaciones->anaquel1;
                $ubicaciones->gaveta1 = $params['gaveta1'] ?? $ubicaciones->gaveta1;
                $ubicaciones->numeroGaveta1 = $params['numeroGaveta1'] ?? $ubicaciones->numeroGaveta1;
                $ubicaciones->ubicacion2 = $params['ubicacion2'] ?? $ubicaciones->ubicacion2;
                $ubicaciones->anaquel2 = $params['anaquel2'] ?? $ubicaciones->anaquel2;
                $ubicaciones->gaveta2 = $params['gaveta2'] ?? $ubicaciones->gaveta2;
                $ubicaciones->numeroGaveta2 = $params['numeroGaveta2'] ?? $ubicaciones->numeroGaveta2;
                $ubicaciones->observaciones = $params['observaciones'] ?? $ubicaciones->observaciones;
                $ubicaciones->status = $params['status'] ?? $ubicaciones->status;

                unset($params['id']);
                unset($params['created_at']);

                $ubicaciones->save();

                $data = array(
                    'ubicaciones' => $ubicaciones,
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

        if($ubicaciones = Ubicaciones::find($id)){

            $ubicaciones->delete();

            $data = array(
                'ubicaciones' => $ubicaciones,
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
}
