<?php

namespace App\Http\Controllers;

use App\Models\Compras;
use App\Models\ComprasDetalles;
use App\Models\Productos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ComprasController extends Controller
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

        $compras = Compras::orderBy('created_at', 'desc')->get();
            $data = array(
                'compras' => $compras,
                'total' => @count($compras),
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

        if (Compras::where('idPuntoVenta', $id)->exists()) {
            $compras = Compras::where('idPuntoVenta', $id)->orderBy('created_at', 'desc')->get();
            // only load details belonging to the filtered compras
            $comprasIds = $compras->pluck('id')->all();
            $detalles = ComprasDetalles::whereIn('idCompra', $comprasIds)->get();

            $data = array(
                'compras' => $compras,
                'detalles' => $detalles,
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

        $compras = new Compras();
        $compras->idPuntoVenta = $params['idPuntoVenta'] ?? null;
        $compras->fechaCompra = $params['fechaCompra'] ?? null;
        $compras->idProveedor = $params['idProveedor'] ?? null;
        $compras->rucProveedor = $params['rucProveedor'] ?? null;
        $compras->nombreProveedor = $params['nombreProveedor'] ?? null;
        $compras->razonSocial = $params['razonSocial'] ?? null;
        $compras->idTipoDocumento = $params['idTipoDocumento'] ?? null;
        $compras->nombreTipoDocumento = $params['nombreTipoDocumento'] ?? null;
        $compras->numeroTipoDocumento = $params['numeroTipoDocumento'] ?? null;
        $compras->procedencia = $params['procedencia'] ?? null;
        $compras->totalCompras = $params['totalCompras'] ?? null;
        $compras->archivo = $params['archivo'] ?? null;
        $compras->observaciones = $params['observaciones'] ?? null;
        $compras->status = $params['status'] ?? null;
        $compras->idUsuario = $user->id;
        $compras->percepcion = $params['percepcion'] ?? null;
        $compras->save();

        foreach ($params['detalles'] ?? [] as $value) {
            $comprasDetalle = new ComprasDetalles();
            $comprasDetalle->idCompra = $compras->id;
            $comprasDetalle->idProducto = $value['idProducto'] ?? null;
            $comprasDetalle->nombre = $value['nombre'] ?? null;
            $comprasDetalle->codigoBarra = $value['codigoBarra'] ?? null;
            $comprasDetalle->precio = $value['precio'] ?? null;
            $comprasDetalle->nuevoPrecio = $value['nuevoPrecio'] ?? null;
            $comprasDetalle->cantidad = $value['cantidad'] ?? null;
            $comprasDetalle->fechaVencimiento = $value['fechaVencimiento'] ?? null;
            $comprasDetalle->loteProducto = $value['loteProducto'] ?? null;
            $comprasDetalle->total = $value['total'] ?? null;
            $comprasDetalle->bonificacion = $value['bonificacion'] ?? null;
            $comprasDetalle->observaciones = $value['observaciones'] ?? null;
            $comprasDetalle->status = 1;
            $comprasDetalle->save();

            $productos = Productos::find($value['idProducto'] ?? null);
            if ($productos) {
                $productos->stockActual = $productos->stockActual + ($value['cantidad'] ?? 0);
                $productos->precioCompra = $value['precio'] ?? $productos->precioCompra;
                $productos->precio = $value['precioVenta'] ?? $productos->precio;
                $productos->precioMinimo = $value['precioMinimo'] ?? $productos->precioMinimo;
                $productos->precioMaximo = $value['precioMaximo'] ?? $productos->precioMaximo;
                $productos->precioMayor = $value['precioMayor'] ?? $productos->precioMayor;

                if (($value['nuevoPrecio'] ?? 0) > 0 || ($value['nuevoPrecio'] ?? '') !== '') {
                    $productos->precioCompra = $value['nuevoPrecio'];
                }
                $productos->save();
            }
        }

        $data = array(
            'compras' => $compras,
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $params = $request->all();

        if($compras = Compras::find($id)){
            $compras->idPuntoVenta = $params['idPuntoVenta'] ?? $compras->idPuntoVenta;
            $compras->fechaCompra = $params['fechaCompra'] ?? $compras->fechaCompra;
            $compras->idProveedor = $params['idProveedor'] ?? $compras->idProveedor;
            $compras->rucProveedor = $params['rucProveedor'] ?? $compras->rucProveedor;
            $compras->nombreProveedor = $params['nombreProveedor'] ?? $compras->nombreProveedor;
            $compras->razonSocial = $params['razonSocial'] ?? $compras->razonSocial;
            $compras->idTipoDocumento = $params['idTipoDocumento'] ?? $compras->idTipoDocumento;
            $compras->nombreTipoDocumento = $params['nombreTipoDocumento'] ?? $compras->nombreTipoDocumento;
            $compras->numeroTipoDocumento = $params['numeroTipoDocumento'] ?? $compras->numeroTipoDocumento;
            $compras->procedencia = $params['procedencia'] ?? $compras->procedencia;
            $compras->totalCompras = $params['totalCompras'] ?? $compras->totalCompras;
            $compras->archivo = $params['archivo'] ?? $compras->archivo;
            $compras->observaciones = $params['observaciones'] ?? $compras->observaciones;
            $compras->percepcion = $params['percepcion'] ?? $compras->percepcion;
            $compras->status = $params['status'] ?? $compras->status;
            $compras->save();

            foreach ($params['detalles'] ?? [] as $value) {
                if(($value['id'] ?? 0) == 0){
                    $detalles = new ComprasDetalles();
                }
                else{
                    $detalles = ComprasDetalles::find($value['id']);
                }

                $detalles->idCompra = $compras->id;
                $detalles->idProducto = $value['idProducto'] ?? null;
                $detalles->nombre = $value['nombre'] ?? null;
                $detalles->codigoBarra = $value['codigoBarra'] ?? null;
                $detalles->precio = $value['precio'] ?? null;
                $detalles->nuevoPrecio = $value['nuevoPrecio'] ?? null;
                $detalles->cantidad = $value['cantidad'] ?? null;
                $detalles->fechaVencimiento = $value['fechaVencimiento'] ?? null;
                $detalles->loteProducto = $value['loteProducto'] ?? null;
                $detalles->total = $value['total'] ?? null;
                $detalles->bonificacion = $value['bonificacion'] ?? null;
                $detalles->observaciones = $value['observaciones'] ?? null;
                $detalles->status = 1;
                $detalles->save();

                $productos = Productos::find($value['idProducto'] ?? null);
                if ($productos) {
                    $productos->stockActual = $productos->stockActual + ($value['cantidad'] ?? 0);

                    if (($value['nuevoPrecio'] ?? 0) > 0 || ($value['nuevoPrecio'] ?? '') !== '') {
                        $productos->precioCompra = $value['nuevoPrecio'];
                    }
                    $productos->save();
                }
            }

            $data = array(
                'compras' => $compras,
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        if($compras = Compras::find($id)){

            foreach ($compras->detalles as $value) {
                $productos = Productos::find($value->idProducto);
                $productos->stockActual = $productos->stockActual + $value->cantidad;
                $productos->save();

                $detalles = ComprasDetalles::find($value->id);
                $detalles->delete();
            }

            $compras->delete();

            $data = array(
                'compras' => $compras,
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $params = $request->all();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $compras = Compras::whereRaw("idPuntoVenta = " . ($params['idPuntoVenta'] ?? 0) . " and DATE_FORMAT(created_at, '%Y-%m-%d') BETWEEN '". date('Y-m-d', strtotime($params['fechaInicio'] ?? '')) . "' AND '". date('Y-m-d', strtotime($params['fechaFin'] ?? '')) . "'")
                    ->orderBy('created_at', 'desc')
                    ->get();
        // only get detalles for the compras returned by the filter
        $comprasIds = $compras->pluck('id')->all();
        $detalles = ComprasDetalles::whereIn('idCompra', $comprasIds)->get();

        $data = array(
            'compras' => $compras,
            'detalles' => $detalles,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }
}
