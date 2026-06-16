<?php

namespace App\Http\Controllers;

use App\Models\AbastecimientoDetalles;
use App\Models\Abastecimientos;
use App\Models\Cajas;
use App\Models\Productos;
use App\Models\PuntoVenta;
use App\Models\Ubigeo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AbastecimientosController extends Controller
{
    public function index()
    {
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

        // Consulta JOIN personalizada solicitada
        $abastecimientos = \DB::table('tbl_abastecimientos')
            ->join('tbl_abastecimientos_detalles', 'tbl_abastecimientos.id', '=', 'tbl_abastecimientos_detalles.idAbastecimiento')
            ->select(
                'tbl_abastecimientos.id',
                'numeroEnvio',
                'tbl_abastecimientos.created_at',
                'puntoVenta',
                'tbl_abastecimientos_detalles.puntoVentaNew',
                'total'
            )
            ->get();

        $data = [
            'abastecimientos' => $abastecimientos,
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $abastecimientos = \DB::table('tbl_abastecimientos')
            ->join('tbl_abastecimientos_detalles', 'tbl_abastecimientos.id', '=', 'tbl_abastecimientos_detalles.idAbastecimiento')
            ->select(
                'tbl_abastecimientos.id',
                'numeroEnvio',
                'idVendedor',
                'vendedor',
                'tbl_abastecimientos.created_at',
                'tbl_abastecimientos.idPuntoVenta',
                'puntoVenta',
                'tbl_abastecimientos_detalles.idPuntoVentaNew',
                'tbl_abastecimientos_detalles.puntoVentaNew',
                'total'
            )->orderby('created_at', 'desc')->distinct()->get();
        $data = [
            'abastecimientos' => $abastecimientos,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function store(Request $request)
    {
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

        $abastecimientos = new Abastecimientos();
        $abastecimientos->idPuntoVenta = $params[0]['idPuntoVenta'];
        $abastecimientos->puntoVenta = $params[0]['puntoVenta'];
        $abastecimientos->numeroEnvio = $params[0]['numeroEnvio'];
        $abastecimientos->total = $params[0]['totalGeneral'];
        $abastecimientos->idVendedor = $user->id;
        $abastecimientos->vendedor = $user->nombre;
        $abastecimientos->save();

        foreach ($params as $value) {
            $productos = Productos::where([['codigoBarra', $value['codigoBarra']], ['idPuntoVenta', $value['idPuntoVentaNew']]])->first();

            if ($productos == null) {
                $data = [
                    'message' => 'En el Punto de Venta de destino no existe el producto.',
                    'error' => 1,
                    'status' => 200,
                ];
            } else {
                $productos->stockActual = $productos->stockActual + $value['cantidad'];
                $productos->save();

                $productos = Productos::where([['codigoBarra', $value['codigoBarra']], ['idPuntoVenta', $value['idPuntoVenta']]])->first();
                $productos->stockActual = $productos->stockActual - $value['cantidad'];
                $productos->save();

                $detalles = new AbastecimientoDetalles();
                $detalles->idAbastecimiento = $abastecimientos->id;
                $detalles->idProducto = $value['idProducto'];
                $detalles->nombre = $value['nombre'];
                $detalles->precioCompra = $value['precioCompra'];
                $detalles->stockActual = $value['stockActual'];
                $detalles->codigoBarra = $value['codigoBarra'];
                $detalles->idPuntoVentaNew = $value['idPuntoVentaNew'];
                $detalles->puntoVentaNew = $value['puntoVentaNew'];
                $detalles->cantidad = $value['cantidad'];
                $detalles->stockEnviar = $value['stockEnviar'];
                $detalles->save();

                $data = [
                    'abastecimientos' => $abastecimientos,
                    'error' => 0,
                    'message' => 'Producto asignado correctamente',
                    'status' => 200,
                ];
            }
        }

        return response()->json($data, $data['status']);
    }

    public function numeroEnvio($id)
    {
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

        $abastecimientos = Abastecimientos::select('numeroEnvio')->where('idPuntoVenta', $id)->max('numeroEnvio');
        $numeroEnvio = str_pad((int) $abastecimientos + 1, 5, '0', STR_PAD_LEFT);
        $data = [
            'numeroEnvio' => $numeroEnvio,
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    // API para obtener detalles de un abastecimiento específico
    public function detalles($id)
    {
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

        $detalles = \DB::table('tbl_abastecimientos_detalles')
            ->where('idAbastecimiento', $id)
            ->get();

        $data = [
            'detalles' => $detalles,
            'total' => count($detalles),
            'status' => 200,
        ];

        return response()->json($data, $data['status']);
    }

    public function reporteAbastecimiento($id)
    {
        $abastecimiento = Abastecimientos::find($id)->load('detalles');
        $puntoVenta = PuntoVenta::find($abastecimiento->idPuntoVenta);
        $ubigeo = Ubigeo::find($puntoVenta->idUbigeo);
        $cajas = Cajas::where('idPuntoVenta', $abastecimiento->idPuntoVenta)->first();
        $pdf = Pdf::loadView('abastecimiento', compact('abastecimiento', 'puntoVenta', 'ubigeo', 'cajas'));

        $GLOBALS['bodyHeight'] = 0;

        $pdf->setCallbacks([
            'myCallbacks' => [
                'event' => 'end_frame',
                'f' => function ($frame) {
                    $node = $frame->get_node();

                    if (strtolower($node->nodeName) === 'body') {
                        $padding_box = $frame->get_padding_box();
                        $GLOBALS['bodyHeight'] += $padding_box['h'];
                    }
                },
            ],
        ]);

        $docHeight = $GLOBALS['bodyHeight'] + 800;
        $pdf->setPaper([0, 0, 227, $docHeight]);

        // $pdf->setPaper('b7', 'portrait');
        return $pdf->download('ABASTECIMIENTO-'.str_pad($abastecimiento->id, 6, '0', STR_PAD_LEFT).'.pdf');
        // return $pdf->stream();
    }
}
