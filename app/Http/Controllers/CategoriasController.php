<?php

namespace App\Http\Controllers;

use App\Models\Categorias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class CategoriasController extends Controller
{
    // Endpoint público para listar categorías activas
    public function categoriasActivas()
    {
        $categorias = Categorias::where([['idPuntoVenta', 13], ['status', 1]])->orderBy('created_at', 'desc')->get();
        $data = array(
            'categorias' => $categorias,
            'total' => count($categorias),
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

        $categorias = Categorias::orderBy('created_at', 'desc')->get();
            $data = array(
                'categorias' => $categorias,
                'total' => @count($categorias),
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

        $categorias = Categorias::where('idPuntoVenta', $id)
                                    ->with(['productos.um', 'productos.categorias', 'productos.puntoventa'])
                                    ->get();

        $data = array(
            'categorias' => $categorias,
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
            return response()->json(['token_expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent'], 401);
        }

            $params = $request->all();

            $categorias = new Categorias();
            $categorias->idPuntoVenta = $params['idPuntoVenta'] ?? null;
            $categorias->nombre = $params['nombre'] ?? null;
            $categorias->observaciones = $params['observaciones'] ?? null;
            $categorias->imagen = $params['imagen'] ?? '';
            $categorias->status = $params['status'] ?? null;
            $categorias->save();

            $data = array(
                'categorias' => $categorias,
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

            if($categorias = Categorias::find($id)){
                $categorias->idPuntoVenta = $params['idPuntoVenta'] ?? $categorias->idPuntoVenta;
                $categorias->nombre = $params['nombre'] ?? $categorias->nombre;
                $categorias->observaciones = $params['observaciones'] ?? $categorias->observaciones;
                $categorias->imagen = $params['imagen'] ?? '';
                $categorias->status = $params['status'] ?? $categorias->status;

                unset($params['id']);
                unset($params['created_at']);

                $categorias->save();

                $data = array(
                    'categorias' => $categorias,
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

        if($categorias = Categorias::find($id)){

            $categorias->delete();

            $data = array(
                'categorias' => $categorias,
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

        /**
     * Subir imagen de categoria y actualizar campo imagen en la base de datos
     */
    public function uploadImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:tbl_categorias,id',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif|max:4096',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $categoria = DB::table('tbl_categorias')->where('id', $request->id)->first();
        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoria no encontrada'
            ], 404);
        }

        $file = $request->file('imagen');
        $nombreArchivo = 'categoria_' . $request->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('categorias'), $nombreArchivo);
        $rutaPublica = 'categorias/' . $nombreArchivo;

        // Actualizar campo imagen
        DB::table('tbl_categorias')->where('id', $request->id)->update(['imagen' => $rutaPublica]);

        return response()->json([
            'success' => true,
            'imagen' => $rutaPublica,
            'message' => 'Imagen subida y categoria actualizado correctamente'
        ], 200);
    }

        /**
     * Eliminar la imagen de un categoria
     */
    public function deleteImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:tbl_categorias,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $categoria = DB::table('tbl_categorias')->where('id', $request->id)->first();
        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoria no encontrado'
            ], 404);
        }

        if (!empty($categoria->imagen)) {
            $rutaImagen = public_path($categoria->imagen);
            if (file_exists($rutaImagen)) {
                unlink($rutaImagen);
            }
        }

        DB::table('tbl_categorias')->where('id', $request->id)->update(['imagen' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada correctamente'
        ], 200);
    }
}
