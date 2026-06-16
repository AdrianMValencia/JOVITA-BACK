<?php

namespace App\Http\Controllers;

use App\Models\SeriesTickets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class SeriesTicketsController extends Controller
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

        $seriesTickets = SeriesTickets::get()->load('puntoventa')->load('numeracion');

        $data = array(
            'seriesTickets' => $seriesTickets,
            'total' => @count($seriesTickets),
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function cargar($id){

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

        $seriesTickets = SeriesTickets::where('idPuntoVenta', $id)->get()->load('puntoventa')->load('numeracion');

        $data = array(
            'seriesTickets' => $seriesTickets,
            'total' => @count($seriesTickets),
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

        if ($seriesTickets = SeriesTickets::where('idPuntoVenta', $id)->get()) {

            $data = array(
                'seriesTickets' => $seriesTickets,
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

            $seriesTickets = new SeriesTickets();
            $seriesTickets->serie = $params['serie'] ?? null;
            $seriesTickets->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $seriesTickets->observaciones = $params['observaciones'] ?? null;
            $seriesTickets->status = $params['status'] ?? null;
            $seriesTickets->save();

            $data = array(
                'seriesTickets' => $seriesTickets,
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

            if($seriesTickets = SeriesTickets::find($id)){
                $seriesTickets->serie = $params['serie'] ?? $seriesTickets->serie;
                $seriesTickets->idPuntoVenta = $params['idPuntoVenta'] ?? $seriesTickets->idPuntoVenta;
                $seriesTickets->observaciones = $params['observaciones'] ?? $seriesTickets->observaciones;
                $seriesTickets->status = $params['status'] ?? $seriesTickets->status;

                unset($params['id']);
                unset($params['created_at']);

                $seriesTickets->save();

                $data = array(
                    'seriesTickets' => $seriesTickets,
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

        if($seriesTickets = SeriesTickets::find($id)){

            $seriesTickets->delete();

            $data = array(
                'seriesTickets' => $seriesTickets,
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
