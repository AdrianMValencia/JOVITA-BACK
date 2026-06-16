<?php

namespace App\Http\Controllers;

use App\Models\PuntoVenta;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class PuntoVentaController extends Controller
{
    public function index()
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

        $puntosVenta = PuntoVenta::with('ubigeos', 'series')->get();
        $data = [
            'puntosVenta' => $puntosVenta,
            'total' => count($puntosVenta),
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function show($id, Request $request)
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

        $puntosVenta = PuntoVenta::with('ubigeos', 'series')->find($id);
        if ($puntosVenta) {
            $data = [
                'puntosVenta' => $puntosVenta,
                'status' => 200,
            ];
        } else {
            $data = [
                'message' => 'Codigo no encontrado',
                'status' => 404,
            ];
        }

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

        $puntosVenta = new PuntoVenta();
        $puntosVenta->nombre = $params['nombre'] ?? null;
        $puntosVenta->direccion = $params['direccion'] ?? null;
        $puntosVenta->idUbigeo = $params['idUbigeo'] ?? null;
        $puntosVenta->telefono = $params['telefono'] ?? null;
        $puntosVenta->celular = $params['celular'] ?? null;
        $puntosVenta->correo = $params['correo'] ?? null;
        $puntosVenta->observaciones = $params['observaciones'] ?? null;
        $puntosVenta->status = $params['status'] ?? null;
        $puntosVenta->save();

        $data = [
            'puntosVenta' => $puntosVenta,
            'message' => 'Registro agregado correctamente',
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function update($id, Request $request)
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

        if ($puntosVenta = PuntoVenta::find($id)) {
            $puntosVenta->nombre = $params['nombre'] ?? $puntosVenta->nombre;
            $puntosVenta->direccion = $params['direccion'] ?? $puntosVenta->direccion;
            $puntosVenta->idUbigeo = $params['idUbigeo'] ?? $puntosVenta->idUbigeo;
            $puntosVenta->telefono = $params['telefono'] ?? $puntosVenta->telefono;
            $puntosVenta->celular = $params['celular'] ?? $puntosVenta->celular;
            $puntosVenta->correo = $params['correo'] ?? $puntosVenta->correo;
            $puntosVenta->observaciones = $params['observaciones'] ?? $puntosVenta->observaciones;
            $puntosVenta->status = $params['status'] ?? $puntosVenta->status;
            $puntosVenta->save();

            $data = [
                'puntosVenta' => $puntosVenta,
                'message' => 'Registro actualizado correctamente.',
                'status' => 200,
            ];
        } else {
            $data = [
                'message' => 'Codigo no encontrado',
                'status' => 404,
            ];
        }

        return response()->json($data, $data['status']);
    }

    public function destroy($id, Request $request)
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

        if ($puntosVenta = PuntoVenta::find($id)) {
            $puntosVenta->delete();

            $data = [
                'puntosVenta' => $puntosVenta,
                'message' => 'Registro eliminado correctamente.',
                'status' => 200,
            ];
        } else {
            $data = [
                'message' => 'Codigo no encontrado',
                'status' => 404,
            ];
        }

        return response()->json($data, $data['status']);
    }

    public function abastecimiento($id, Request $request)
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

        $puntosVenta = PuntoVenta::whereNotIn('id', [$id])->get()->load('ubigeos');
        $data = [
            'puntosVenta' => $puntosVenta,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function puntoVentaActivos()
    {
        $puntosVenta = PuntoVenta::where('status', 1)->whereNotIn('id', [9, 13, 10])->orderBy('nombre', 'asc')->get()->load('ubigeos')->load('series');
        $data = [
            'puntosVenta' => $puntosVenta,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }
}
