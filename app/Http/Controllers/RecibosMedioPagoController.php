<?php

namespace App\Http\Controllers;

use App\Models\RecibosMedioPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
class RecibosMedioPagoController extends Controller
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

        $recibosMedioPago = RecibosMedioPago::get()->load('recibos')->load('medioPagos');
            $data = array(
                'recibosMedioPago' => $recibosMedioPago,
                'total' => @count($recibosMedioPago),
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

        if ($recibosMedioPago = RecibosMedioPago::where('idRecibo', $id)->first()) {

            $data = array(
                'recibosMedioPago' => $recibosMedioPago,
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

            $recibosMedioPago = new RecibosMedioPago();
            $recibosMedioPago->idRecibo = $params['idRecibo'] ?? null;
            $recibosMedioPago->idMedioPago = $params['idMedioPago'] ?? null;
            $recibosMedioPago->importe = $params['importe'] ?? null;
            $recibosMedioPago->nota = $params['nota'] ?? null;
            $recibosMedioPago->save();

            $data = array(
                'recibosMedioPago' => $recibosMedioPago,
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

            if($recibosMedioPago = RecibosMedioPago::find($id)){
                $recibosMedioPago->idRecibo = $params['idRecibo'] ?? $recibosMedioPago->idRecibo;
                $recibosMedioPago->idMedioPago = $params['idMedioPago'] ?? $recibosMedioPago->idMedioPago;
                $recibosMedioPago->importe = $params['importe'] ?? $recibosMedioPago->importe;
                $recibosMedioPago->nota = $params['nota'] ?? $recibosMedioPago->nota;

                unset($params['id']);
                unset($params['created_at']);

                $recibosMedioPago->save();

                $data = array(
                    'recibosMedioPago' => $recibosMedioPago,
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

        if($recibosMedioPago = RecibosMedioPago::find($id)){

            $recibosMedioPago->delete();

            $data = array(
                'recibosMedioPago' => $recibosMedioPago,
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

    public function recibosMedioPagoDia($idPuntoVenta, $idUsuario, $dia){

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

        if($idUsuario == 0){
            // $recibosMedioPago = RecibosMedioPago::selectRaw('tbl_recibo_medio_pago.*')
            // ->join('tbl_recibos', 'tbl_recibo_medio_pago.idRecibo', '=', 'tbl_recibos.id')
            // ->whereRaw("tbl_recibos.idPuntoVenta = ". $idPuntoVenta ." and DATE_FORMAT(tbl_recibo_medio_pago.created_at, '%d%m') = ". $dia ."")
            // ->get()


            $recibosMedioPago = RecibosMedioPago::selectRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') fechaEmision, SUM(tbl_recibo_medio_pago.importe) importe")
            ->join('tbl_recibos', 'tbl_recibo_medio_pago.idRecibo', '=', 'tbl_recibos.id')
            ->join('tbl_tipos_pago', 'tbl_recibo_medio_pago.idMedioPago', '=', 'tbl_tipos_pago.id')
            ->whereRaw("tbl_recibos.idPuntoVenta = ". $idPuntoVenta . " and DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($dia)) . "'")
            ->groupByRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d')")
            ->get();

                $data = array(
                    'recibosMedioPago' => $recibosMedioPago,
                    'total' => @count($recibosMedioPago),
                    'status' => 200
                );;

        }else{

          //  $recibosMedioPago = RecibosMedioPago::selectRaw('tbl_recibo_medio_pago.*')
            //->join('tbl_recibos', 'tbl_recibo_medio_pago.idRecibo', '=', 'tbl_recibos.id')
            //->whereRaw("tbl_recibos.idPuntoVenta = ". $idPuntoVenta ." and tbl_recibos.idUsuario = ". $idUsuario ." and DATE_FORMAT(tbl_recibo_medio_pago.created_at, '%d%m') = ". $dia ."")
            //->get()->load('recibos')->load('medioPagos');

             $recibosMedioPago = RecibosMedioPago::selectRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') fechaEmision, SUM(tbl_recibo_medio_pago.importe) importe")
            ->join('tbl_recibos', 'tbl_recibo_medio_pago.idRecibo', '=', 'tbl_recibos.id')
            ->join('tbl_tipos_pago', 'tbl_recibo_medio_pago.idMedioPago', '=', 'tbl_tipos_pago.id')
            ->whereRaw("tbl_recibos.idUsuario = ". $idUsuario ." and tbl_recibos.idPuntoVenta = ". $idPuntoVenta . " and DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d') = '". date('Y-m-d', strtotime($dia)) . "'")
            ->groupByRaw("tbl_tipos_pago.nombre, DATE_FORMAT(tbl_recibos.created_at,'%Y-%m-%d')")
            ->get();

                $data = array(
                    'recibosMedioPago' => $recibosMedioPago,
                    'total' => @count($recibosMedioPago),
                    'status' => 200
                );
        }

        return response()->json($data, $data['status']);
    }
}
