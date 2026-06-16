<?php

namespace App\Http\Controllers;

use App\Models\TiposPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class TiposPagoController extends Controller
{
    private const CACHE_TTL_MINUTES = 60;

    private function indexCacheKey(): string
    {
        return 'tipos_pago:index';
    }

    private function puntoVentaCacheKey($id): string
    {
        return 'tipos_pago:punto_venta:'.$id;
    }

    private function forgetTiposPagoCache($idPuntoVenta = null): void
    {
        Cache::forget($this->indexCacheKey());

        if (!is_null($idPuntoVenta)) {
            Cache::forget($this->puntoVentaCacheKey($idPuntoVenta));
        }
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

        $tiposPago = Cache::remember(
            $this->indexCacheKey(),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => TiposPago::select(['id', 'idPuntoVenta', 'nombre', 'observaciones', 'status', 'created_at', 'updated_at'])
                ->orderBy('nombre', 'asc')
                ->get()
        );

            $data = array(
                'tiposPago' => $tiposPago,
                'total' => count($tiposPago),
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

        $tiposPago = Cache::remember(
            $this->puntoVentaCacheKey($id),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => TiposPago::select(['id', 'idPuntoVenta', 'nombre', 'observaciones', 'status', 'created_at', 'updated_at'])
                ->where('idPuntoVenta', $id)
                ->orderBy('nombre', 'asc')
                ->get()
        );

        $data = array(
            'tiposPago' => $tiposPago,
            'status' => 200
        );

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

            $tiposPago = new TiposPago();
            $tiposPago->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $tiposPago->nombre = $params['nombre'] ?? null;
            $tiposPago->observaciones = $params['observaciones'] ?? null;
            $tiposPago->status = $params['status'] ?? null;
            $tiposPago->save();

            $this->forgetTiposPagoCache($tiposPago->idPuntoVenta);

            $data = array(
                'tiposPago' => $tiposPago,
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

            if($tiposPago = TiposPago::find($id)){
                $previousIdPuntoVenta = $tiposPago->idPuntoVenta;
                $tiposPago->idPuntoVenta = $params['idPuntoVenta'] ?? $tiposPago->idPuntoVenta;
                $tiposPago->nombre = $params['nombre'] ?? $tiposPago->nombre;
                $tiposPago->observaciones = $params['observaciones'] ?? $tiposPago->observaciones;
                $tiposPago->status = $params['status'] ?? $tiposPago->status;

                unset($params['id']);
                unset($params['created_at']);

                $tiposPago->save();

                $this->forgetTiposPagoCache($previousIdPuntoVenta);
                if ($previousIdPuntoVenta != $tiposPago->idPuntoVenta) {
                    $this->forgetTiposPagoCache($tiposPago->idPuntoVenta);
                }

                $data = array(
                    'tiposPago' => $tiposPago,
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

        if($tiposPago = TiposPago::find($id)){
            $idPuntoVenta = $tiposPago->idPuntoVenta;

            $tiposPago->delete();

            $this->forgetTiposPagoCache($idPuntoVenta);

            $data = array(
                'tiposPago' => $tiposPago,
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
