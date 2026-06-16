<?php

namespace App\Http\Controllers;

use App\Models\Valorizado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class ValorizadoController extends Controller
{

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

        if ($id == '10') {
            $valorizado = Valorizado::whereIn('idPuntoVenta', [6, 7, 8, 11])->orderBy('created_at', 'desc')->get();
        }else{
            $valorizado = Valorizado::where('idPuntoVenta', $id)->orderBy('created_at', 'desc')->get();
        }

        $data = array(
            'valorizado' => $valorizado,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $insertar = DB::select("SELECT idPuntoVenta, nombrePuntoVenta as puntoVenta, SUM(stockActual * precioCompra) as valorizado FROM `tbl_productos` WHERE status = 1 group by idPuntoVenta;");

        foreach ($insertar as $value) {
            $valorizado = new Valorizado();
            $valorizado->idPuntoVenta = $value->idPuntoVenta;
            $valorizado->puntoVenta = $value->puntoVenta;
            $valorizado->valorizado = $value->valorizado;
            $valorizado->save();
        }
    }
}
