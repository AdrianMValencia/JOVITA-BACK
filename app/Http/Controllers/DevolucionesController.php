<?php

namespace App\Http\Controllers;

use App\Models\Devoluciones;
use App\Models\Productos;
use App\Models\PuntoVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class DevolucionesController extends Controller
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

        $devoluciones = Devoluciones::get();
            $data = array(
                'devoluciones' => $devoluciones,
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

        $puntosVenta = PuntoVenta::whereNotIn('id', [$id])->get()->load('ubigeos');
        $data = array(
            'puntosVenta' => $puntosVenta,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function store(Request $request)
    {
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

        foreach ($params as $value) {
            $devoluciones = new Devoluciones();
            $devoluciones->idPuntoVenta = $value['idPuntoVenta'] ?? null;
            $devoluciones->puntoVenta = $value['puntoVenta'] ?? null;
            $devoluciones->idRecibos = $value['idRecibos'] ?? null;
            $devoluciones->idProducto = $value['idProducto'] ?? null;
            $devoluciones->nombre = $value['nombre'] ?? null;
            $devoluciones->stockActual = $value['stockActual'] ?? null;
            $devoluciones->codigoBarra = $value['codigoBarra'] ?? null;
            $devoluciones->idPuntoVentaNew = $value['idPuntoVentaNew'] ?? null;
            $devoluciones->puntoVentaNew = $value['puntoVentaNew'] ?? null;
            $devoluciones->cantidad = $value['cantidad'] ?? null;
            $devoluciones->motivo = $value['motivo'] ?? null;
            $devoluciones->save();

            $productos = Productos::where([
                ['codigoBarra', $value['codigoBarra'] ?? null],
                ['idPuntoVenta', $value['idPuntoVentaNew'] ?? null]
            ])->first();
            if ($productos) {
                $productos->stockActual = $productos->stockActual + ($value['cantidad'] ?? 0);
                $productos->save();
            }

            $productos = Productos::where([
                ['codigoBarra', $value['codigoBarra'] ?? null],
                ['idPuntoVenta', $value['idPuntoVenta'] ?? null]
            ])->first();
            if ($productos) {
                $productos->stockActual = $productos->stockActual - ($value['cantidad'] ?? 0);
                $productos->save();
            }
        }

        $data = array(
            'devoluciones' => $devoluciones,
            'message' => 'El producto se devolvio correctamente',
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }
}
