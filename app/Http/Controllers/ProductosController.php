<?php

namespace App\Http\Controllers;

use App\Models\Productos;
use App\Models\PuntoVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Twilio\Rest\Client;

class ProductosController extends Controller
{
    // Endpoint público para listar productos activos
    public function productosActivos()
    {
        $productos = Productos::where([['idPuntoVenta', 13], ['status', 1]])->orderBy('created_at', 'desc')->get();
        $data = array(
            'productos' => $productos,
            'total' => count($productos),
            'status' => 200
        );
        return response()->json($data, $data['status']);
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

        $page = request()->query('page', 1);
        $perPage = request()->query('perPage', 20);

        if ($page < 1) {
            $page = 1;
        }

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $offset = ($page - 1) * $perPage;

        $total = Productos::count();
        $productos = Productos::orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $data = array(
            'productos' => $productos,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
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

        $page = isset($request->page) ? (int) $request->page : 1;
        $perPage = isset($request->perPage) ? (int) $request->perPage : 20;

        if ($page < 1) {
            $page = 1;
        }

        if ($perPage < 1) {
            $perPage = 20;
        }

        if ($perPage > 100) {
            $perPage = 100;
        }

        $offset = ($page - 1) * $perPage;

        $totalResult = Productos::where('idPuntoVenta', $id)->count();
        
        if ($totalResult > 0) {
            $productos = Productos::where('idPuntoVenta', $id)
                ->with('categorias', 'um', 'puntoventa', 'proveedores')
                ->limit($perPage)
                ->offset($offset)
                ->get();

            $lastPage = $totalResult > 0 ? (int) ceil($totalResult / $perPage) : 1;

            $data = array(
                'productos' => $productos,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $totalResult,
                    'lastPage' => $lastPage,
                ],
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

        $puntosVenta = PuntoVenta::get();
        foreach ($puntosVenta as $value) {
            $productos = new Productos();
            $productos->idPuntoVenta = $value->id;
            $productos->nombrePuntoVenta = $value->nombre;
            $productos->nombre = $params['nombre'] ?? null;
            $productos->codigoAntiguo = $params['codigoAntiguo'] ?? null;
            $productos->codigoBarra = $params['codigoBarra'] ?? null;
            $productos->idCategoria = $params['idCategoria'] ?? null;
            $productos->nombreCategoria = $params['nombreCategoria'] ?? null;
            $productos->idUm = $params['idUm'] ?? null;
            $productos->nombreUm = $params['nombreUm'] ?? null;
            $productos->stockMinimo = $params['stockMinimo'] ?? null;
            $productos->stockMaximo = $params['stockMaximo'] ?? null;
            $productos->stockActual = $params['stockActual'] ?? null;
            $productos->stockAlerta = $params['stockAlerta'] ?? null;
            $productos->precio = $params['precio'] ?? null;
            $productos->precioMinimo = $params['precioMinimo'] ?? null;
            $productos->precioMaximo = $params['precioMaximo'] ?? null;
            $productos->precioMayor = $params['precioMayor'] ?? null;
            $productos->precioCompra = $params['precioCompra'] ?? null;
            $productos->observaciones = $params['observaciones'] ?? null;
            $productos->slider = $params['slider'] ?? false;
            $productos->banner = $params['banner'] ?? 0;
            $productos->descuento = $params['descuento'] ?? 0.00;
            $productos->igv = array_key_exists('igv', $params) ? filter_var($params['igv'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true;
            if (is_null($productos->igv)) {
                $productos->igv = true;
            }
            $productos->status = $params['status'] ?? null;
            $productos->save();
        }

        $data = array(
            'productos' => $productos,
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

        if($productos = Productos::find($id)){
            $sid    = env( 'TWILIO_SID' );
            $token  = env( 'TWILIO_TOKEN' );
            $client = new Client( $sid, $token );

            $productos->idPuntoVenta = $params['idPuntoVenta'] ?? $productos->idPuntoVenta;
            $productos->nombrePuntoVenta = $params['nombrePuntoVenta'] ?? $productos->nombrePuntoVenta;
            $productos->nombre = $params['nombre'] ?? $productos->nombre;
            $productos->codigoAntiguo = $params['codigoAntiguo'] ?? $productos->codigoAntiguo;
            $productos->codigoBarra = $params['codigoBarra'] ?? $productos->codigoBarra;
            $productos->idCategoria = $params['idCategoria'] ?? $productos->idCategoria;
            $productos->nombreCategoria = $params['nombreCategoria'] ?? $productos->nombreCategoria;
            $productos->idUm = $params['idUm'] ?? $productos->idUm;
            $productos->nombreUm = $params['nombreUm'] ?? $productos->nombreUm;
            $productos->stockMinimo = $params['stockMinimo'] ?? $productos->stockMinimo;
            $productos->stockMaximo = $params['stockMaximo'] ?? $productos->stockMaximo;
            $productos->stockActual = $params['stockActual'] ?? $productos->stockActual;
            $productos->stockAlerta = $params['stockAlerta'] ?? $productos->stockAlerta;
            $productos->precio = $params['precio'] ?? $productos->precio;
            $productos->precioMinimo = $params['precioMinimo'] ?? $productos->precioMinimo;
            $productos->precioMaximo = $params['precioMaximo'] ?? $productos->precioMaximo;
            $productos->precioMayor = $params['precioMayor'] ?? $productos->precioMayor;
            $productos->precioCompra = $params['precioCompra'] ?? $productos->precioCompra;
            $productos->observaciones = $params['observaciones'] ?? $productos->observaciones;
            $productos->slider = $params['slider'] ?? $productos->slider;
            $productos->banner = $params['banner'] ?? 0;
            $productos->descuento = $params['descuento'] ?? $productos->descuento;
            if (array_key_exists('igv', $params)) {
                $igv = filter_var($params['igv'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $productos->igv = is_null($igv) ? $productos->igv : $igv;
            }
            $productos->status = $params['status'] ?? $productos->status;

            $productos->save();

            $data = array(
                'productos' => $productos,
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

    public function destroy($id){

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

        if($productos = Productos::find($id)){

            // $sid    = env( 'TWILIO_SID' );
            // $token  = env( 'TWILIO_TOKEN' );
            // $client = new Client( $sid, $token );

            // $client->messages->create(
            //     env( 'NUMERO1' ),
            //     [
            //         'from' => env( 'TWILIO_FROM' ),
            //         'body' => "Se ha eliminado el producto ". $productos->nombre .", lo hizo el usuario ". $user->nombre ."",
            //     ]
            // );
            // $client->messages->create(
            //     env( 'NUMERO2' ),
            //     [
            //         'from' => env( 'TWILIO_FROM' ),
            //         'body' => "Se ha eliminado el producto ". $productos->nombre .", lo hizo el usuario ". $user->nombre ."",
            //     ]
            // );

            $productos->delete();

            $data = array(
                'productos' => $productos,
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

    public function imprimir($id){
        $productos = Productos::find($id);
        $generator = new BarcodeGeneratorPNG();
        $barcodeData = $generator->getBarcode($productos->codigoBarra, $generator::TYPE_CODE_128);
        return base64_encode($barcodeData);
   }
    /**
     * Subir imagen de producto y actualizar campo imagen en la base de datos
     */
    public function uploadImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:tbl_productos,id',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $producto = DB::table('tbl_productos')->where('id', $request->id)->first();
        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $file = $request->file('imagen');
        $nombreArchivo = 'producto_' . $request->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('productos'), $nombreArchivo);
        $rutaPublica = 'productos/' . $nombreArchivo;

        // Actualizar campo imagen
        DB::table('tbl_productos')->where('id', $request->id)->update(['imagen' => $rutaPublica]);

        return response()->json([
            'success' => true,
            'imagen' => $rutaPublica,
            'message' => 'Imagen subida y producto actualizado correctamente'
        ], 200);
    }

        /**
     * Eliminar la imagen de un producto
     */
    public function deleteImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:tbl_productos,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $producto = DB::table('tbl_productos')->where('id', $request->id)->first();
        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        if (!empty($producto->imagen)) {
            $rutaImagen = public_path($producto->imagen);
            if (file_exists($rutaImagen)) {
                unlink($rutaImagen);
            }
        }

        DB::table('tbl_productos')->where('id', $request->id)->update(['imagen' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada correctamente'
        ], 200);
    }

    public function buscarProductos($id, $texto, Request $request){
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

        $texto = trim(rawurldecode((string) $texto));
        if ($texto === '') {
            return response()->json([
                'productos' => [],
                'status' => 200,
            ], 200);
        }

        if (function_exists('mb_substr')) {
            $texto = mb_substr($texto, 0, 120, 'UTF-8');
        } else {
            $texto = substr($texto, 0, 120);
        }

        $limite = (int) $request->query('limite', 80);
        if ($limite < 1) {
            $limite = 80;
        }
        if ($limite > 200) {
            $limite = 200;
        }

        $likeNombre = '%' . $texto . '%';
        $prefijoCodigo = $texto . '%';

        $productos = Productos::query()
            ->where('idPuntoVenta', $id)
            ->where('status', 1)
            ->where(function ($q) use ($likeNombre, $prefijoCodigo) {
                $q->where('nombre', 'like', $likeNombre)
                    ->orWhere('codigoBarra', 'like', $prefijoCodigo)
                    ->orWhere('codigoAntiguo', 'like', $prefijoCodigo);
            })
            ->orderBy('nombre')
            ->limit($limite)
            ->get();

        $data = array(
            'productos' => $productos,
            'status' => 200,
        );

        return response()->json($data, $data['status']);
    }

    public function codigoBarras($id, $codigo, Request $request){
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

        if ($productos = Productos::where([['idPuntoVenta', $id], ['codigoBarra', $codigo], ['status', 1]])->first()) {

            $data = array(
                'productos' => $productos,
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

    public function cargarProductosVentas($id, Request $request){

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

        $productos = Productos::where([['idPuntoVenta', $id], ['status', 1]])->get();
        $data = array(
            'productos' => $productos,
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    // API externa para búsqueda pública de productos activos
    public function productosBuscar(Request $request)
    {
        $query = $request->input('q');
        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'El parámetro q es requerido.'
            ], 422);
        }

        $categoria = $request->input('categoria');
        $limit = intval($request->input('limit', 10));
        $page = intval($request->input('page', 1));
        $offset = ($page - 1) * $limit;

        $productosQuery = \App\Models\Productos::where('status', 1)
            ->where('nombre', 'like', "%" . $query . "%");
        if ($categoria) {
            $productosQuery->where('nombreCategoria', $categoria);
        }

        $total = $productosQuery->count();
        $productos = $productosQuery
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $data = $productos->map(function($p) {
            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'precio' => $p->precio,
                'imagen' => $p->imagen ?? '',
                'nombreCategoria' => $p->nombreCategoria,
                // ...otros campos si se requieren
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'pages' => $limit > 0 ? ceil($total / $limit) : 1
        ]);
    }
}
