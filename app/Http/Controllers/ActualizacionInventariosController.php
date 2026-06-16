<?php

namespace App\Http\Controllers;

use App\Models\ActualizacionInventarios;
use App\Models\ActualizacionInventariosDetalles;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ActualizacionInventariosController extends Controller
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

        $inventarios = ActualizacionInventarios::orderBy('nombre', 'asc')->get();
            $data = array(
                'inventarios' => $inventarios,
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

        if ($inventarios = ActualizacionInventarios::find($id)->load('detalles')) {

            $data = array(
                'inventarios' => $inventarios,
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

            $inventarios = new ActualizacionInventarios();
            $inventarios->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $inventarios->puntoVenta = $params['puntoVenta'] ?? null;
            $inventarios->idCategoria = $params['idCategoria'] ?? null;
            $inventarios->categoria = $params['categoria'] ?? null;
            $inventarios->fechaInicio = $params['fechaInicio'] ?? null;
            $inventarios->fechaFin = $params['fechaFin'] ?? null;
            $inventarios->save();

            foreach ($params['detalles'] ?? [] as $value) {
                $detalles = new ActualizacionInventariosDetalles();
                $detalles->idInventario = $inventarios->id;
                $detalles->idCategoria = $value['idCategoria'] ?? null;
                $detalles->categoria = $value['categoria'] ?? null;
                $detalles->codigoBarra = $value['codigoBarra'] ?? null;
                $detalles->idProducto = $value['idProducto'] ?? null;
                $detalles->productos = $value['productos'] ?? null;
                $detalles->stockActual = $value['stockActual'] ?? null;
                $detalles->stockInventario = $value['stockInventario'] ?? null;
                $detalles->diferenciaCantidad = $value['diferenciaCantidad'] ?? null;
                $detalles->precio = $value['precio'] ?? null;
                $detalles->diferenciaPrecio = $value['diferenciaPrecio'] ?? null;
                $detalles->save();

                $productos = Productos::find($value['idProducto'] ?? null);
                if ($productos) {
                    $productos->stockActual = $value['stockInventario'] ?? $productos->stockActual;
                    $productos->save();
                }
            }

            $data = array(
                'inventarios' => $inventarios,
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

            if($inventarios = ActualizacionInventarios::find($id)){
                $inventarios->idCategoria = $params['idCategoria'] ?? $inventarios->idCategoria;
                $inventarios->categoria = $params['categoria'] ?? $inventarios->categoria;
                $inventarios->fechaInicio = $params['fechaInicio'] ?? $inventarios->fechaInicio;
                $inventarios->fechaFin = $params['fechaFin'] ?? $inventarios->fechaFin;

                foreach ($params['detalles'] ?? [] as $value) {
                    if ($value === 0) {
                        $detalles = new ActualizacionInventariosDetalles();
                    }else{
                        $detalles = ActualizacionInventariosDetalles::find($value['id'] ?? null);
                        if (!$detalles) {
                            $detalles = new ActualizacionInventariosDetalles();
                        }
                    }
                    $detalles->idInventario = $inventarios->id;
                    $detalles->idCategoria = $value['idCategoria'] ?? null;
                    $detalles->categoria = $value['categoria'] ?? null;
                    $detalles->codigoBarra = $value['codigoBarra'] ?? null;
                    $detalles->idProducto = $value['idProducto'] ?? null;
                    $detalles->productos = $value['productos'] ?? null;
                    $detalles->stockActual = $value['stockActual'] ?? null;
                    $detalles->stockInventario = $value['stockInventario'] ?? null;
                        $detalles->diferenciaCantidad = isset($value['diferenciaCantidad']) ? $value['diferenciaCantidad'] : null;
                        $detalles->precio = isset($value['precio']) ? $value['precio'] : null;
                        $detalles->diferenciaPrecio = isset($value['diferenciaPrecio']) ? $value['diferenciaPrecio'] : null;
                    $detalles->save();
                }

                unset($params['id']);
                unset($params['created_at']);

                $inventarios->save();

                $data = array(
                    'inventarios' => $inventarios,
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

        if($inventarios = ActualizacionInventarios::find($id)){

            $detalles = ActualizacionInventariosDetalles::where('idInventario', $id)->get();
            foreach ($detalles as $detalle) {
                $detalle->delete();
            }

            $inventarios->delete();

            $data = array(
                'inventarios' => $inventarios,
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

    public function buscarPorFecha(Request $request){

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

            $inventarios = ActualizacionInventarios::whereRaw(
                "idPuntoVenta = " . ($params['idPuntoVenta'] ?? 0) .
                " and DATE_FORMAT(created_at, '%Y-%m-%d') BETWEEN '" . date('Y-m-d', strtotime($params['fechaInicio'] ?? '1970-01-01')) . "' AND '" . date('Y-m-d', strtotime($params['fechaFin'] ?? '2099-12-31')) . "'"
            )
                ->orderBy('created_at', 'desc')
                ->get();

            $data = array(
                'inventarios' => $inventarios,
                'status' => 200
            );

            return response()->json($data, $data['status']);
    }

    public function buscarProductos(Request $request){
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

            $productos = Productos::whereRaw(
                "idPuntoVenta = " . ($params['idPuntoVenta'] ?? 0) .
                " and idCategoria = " . ($params['idCategoria'] ?? 0)
            )
                ->orderBy('created_at', 'desc')
                ->get();
            $data = array(
                'productos' => $productos,
                'status' => 200
            );

            return response()->json($data, $data['status']);
    }
}
