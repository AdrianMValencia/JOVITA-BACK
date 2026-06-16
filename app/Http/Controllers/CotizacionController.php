<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalles;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Barryvdh\DomPDF\Facade\Pdf;

class CotizacionController extends Controller
{
    public function index(){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $cotizacion = Cotizacion::orderBy('created_at', 'desc')->get();
            $data = array(
                'cotizacion' => $cotizacion,
                'total' => @count($cotizacion),
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        if ($cotizacion = Cotizacion::where('idPuntoVenta', $id)->get()->load('detalles')->load('clientes')->load('puntoventa')) {

            $data = array(
                'cotizacion' => $cotizacion,
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $params = $request->all();

        $cotizacion = new Cotizacion();
        $cotizacion->idPuntoVenta = $params['idPuntoVenta'] ?? null;
        $cotizacion->puntoventa = $params['puntoventa'] ?? null;
        $cotizacion->idCliente = $params['idCliente'] ?? null;
        $cotizacion->documento = $params['documento'] ?? null;
        $cotizacion->razonSocial = $params['razonSocial'] ?? null;
        $cotizacion->fechaCotizacion = $params['fechaCotizacion'] ?? null;
        $cotizacion->numero = $params['numero'] ?? null;
        $cotizacion->subtotal = $params['subtotal'] ?? null;
        $cotizacion->impuesto = $params['impuesto'] ?? null;
        $cotizacion->total = $params['total'] ?? null;
        $cotizacion->status = $params['status'] ?? null;
        $cotizacion->save();

        foreach ($params['detalles'] ?? [] as $value) {
            $detalles = new CotizacionDetalles();
            $detalles->idCotizacion = $cotizacion->id;
            $detalles->idProducto = $value['idProducto'] ?? null;
            $detalles->nombre = $value['nombre'] ?? null;
            $detalles->precio = $value['precio'] ?? null;
            $detalles->cantidad = $value['cantidad'] ?? null;
            $detalles->subtotal = $value['subtotal'] ?? null;
            $detalles->igv = $value['igv'] ?? null;
            $detalles->total = $value['total'] ?? null;
            $detalles->porcentajeDesc = $value['porcentajeDesc'] ?? null;
            $detalles->montoDesc = $value['montoDesc'] ?? null;
            $detalles->descripcion = $value['descripcion'] ?? null;
            $detalles->status = 1;
            $detalles->save();

            $productos = Productos::find($value['idProducto'] ?? null);
            if ($productos) {
                $productos->stockActual = $productos->stockActual - ($value['cantidad'] ?? 0);
                $productos->save();
            }
        }

        $data = array(
            'cotizacion' => $cotizacion,
            'message' => 'La Cotización ' . $cotizacion->numero . ' se a generado correctamente.',
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $params = $request->all();

        if($cotizacion = Cotizacion::find($id)){
            $cotizacion->idPuntoVenta = $params['idPuntoVenta'] ?? $cotizacion->idPuntoVenta;
            $cotizacion->puntoventa = $params['puntoventa'] ?? $cotizacion->puntoventa;
            $cotizacion->idCliente = $params['idCliente'] ?? $cotizacion->idCliente;
            $cotizacion->documento = $params['documento'] ?? $cotizacion->documento;
            $cotizacion->razonSocial = $params['razonSocial'] ?? $cotizacion->razonSocial;
            $cotizacion->fechaCotizacion = $params['fechaCotizacion'] ?? $cotizacion->fechaCotizacion;
            $cotizacion->numero = $params['numero'] ?? $cotizacion->numero;
            $cotizacion->subtotal = $params['subtotal'] ?? $cotizacion->subtotal;
            $cotizacion->impuesto = $params['impuesto'] ?? $cotizacion->impuesto;
            $cotizacion->total = $params['total'] ?? $cotizacion->total;
            $cotizacion->status = $params['status'] ?? $cotizacion->status;
            $cotizacion->save();

            foreach ($params['detalles'] ?? [] as $value) {
                if(($value['id'] ?? 0) == 0){
                    $detalles = new CotizacionDetalles();
                    $detalles->idCotizacion = $cotizacion->id;
                    $detalles->idProducto = $value['idProducto'] ?? null;
                    $detalles->nombre = $value['nombre'] ?? null;
                    $detalles->precio = $value['precio'] ?? null;
                    $detalles->cantidad = $value['cantidad'] ?? null;
                    $detalles->subtotal = $value['subtotal'] ?? null;
                    $detalles->igv = $value['igv'] ?? null;
                    $detalles->total = $value['total'] ?? null;
                    $detalles->porcentajeDesc = $value['porcentajeDesc'] ?? null;
                    $detalles->montoDesc = $value['montoDesc'] ?? null;
                    $detalles->descripcion = $value['descripcion'] ?? null;
                    $detalles->status = 1;
                    $detalles->save();
                }else{
                    $detalles = CotizacionDetalles::find($value['id']);
                    $detalles->idProducto = $value['idProducto'] ?? $detalles->idProducto;
                    $detalles->nombre = $value['nombre'] ?? $detalles->nombre;
                    $detalles->precio = $value['precio'] ?? $detalles->precio;
                    $detalles->cantidad = $value['cantidad'] ?? $detalles->cantidad;
                    $detalles->subtotal = $value['subtotal'] ?? $detalles->subtotal;
                    $detalles->igv = $value['igv'] ?? $detalles->igv;
                    $detalles->total = $value['total'] ?? $detalles->total;
                    $detalles->porcentajeDesc = $value['porcentajeDesc'] ?? $detalles->porcentajeDesc;
                    $detalles->montoDesc = $value['montoDesc'] ?? $detalles->montoDesc;
                    $detalles->descripcion = $value['descripcion'] ?? $detalles->descripcion;
                    $detalles->status = 1;
                    $detalles->save();
                }
            }

            $data = array(
                'cotizacion' => $cotizacion,
                'message' => 'La Cotización ' . $cotizacion->numero . ' se a modificado correctamente.',
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        if($cotizacion = Cotizacion::find($id)){

            $cotizacion->delete();

            $data = array(
                'cotizacion' => $cotizacion,
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

    public function cambiarEstado($id, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                    return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $params = $request->all();

        if($cotizacion = Cotizacion::find($id)){
            $cotizacion->status = $params['status'] ?? $cotizacion->status;
            $cotizacion->save();

            $data = array(
                'cotizacion' => $cotizacion,
                'message' => 'El estado de la la Cotización ' . $cotizacion->numero . ' se a modificado correctamente.',
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

    public function reporteCotizacion($id){
        $cotizacion = Cotizacion::find($id)->load('clientes')->load('detalles');

        $pdf = PDF::loadView('cotizacion', compact('cotizacion'));
        return $pdf->download($cotizacion->numero . '.pdf');
    }
}
