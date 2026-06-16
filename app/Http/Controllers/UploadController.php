<?php

namespace App\Http\Controllers;

use App\Models\Proveedores;
use App\Models\Clientes;
use App\Models\Depositos;
use App\Models\Compras;
use App\Models\User;
use App\Models\DatosEmpresa;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UploadController extends Controller
{
    public function upload(Request $request){

        $file = $request->file('imagen');
        $tipo = $request->tipo;
        $id = $request->id;

        if ($file) {
            $imagen_path = $id.'-'.time().'-'.$file->getClientOriginalName();
            Storage::disk($tipo)->put($imagen_path, \File::get($file));
            return $this->subirPorTipo($tipo, $id, $imagen_path);

        }else{
            $data = array(
                'status' => 404,
                'message' => 'Debe seleccionar una imagen.'
            );
        }
        return response()->json($data, $data['status']);
    }

    public function subirPorTipo($tipo, $id, $nombre_path){

        if ($tipo == 'proveedor') {

            if($proveedores = Proveedores::find($id)){
                $proveedores->imagen = $nombre_path;
                $proveedores->save();

                $data = array(
                    'proveedores' => $proveedores,
                    'message' => 'Imágen actualizada correctamente!!!',
                    'status' => 200
                );
            }else{
                $data = array(
                    'message' => 'Usuario no existe.',
                    'status' => 404
                );
            }

        }else if ($tipo == 'clientes') {

            if($clientes = Clientes::find($id)){
                $clientes->imagen = $nombre_path;
                $clientes->save();

                $data = array(
                    'clientes' => $clientes,
                    'message' => 'Imágen actualizada correctamente!!!',
                    'status' => 200
                );
            }else{
                $data = array(
                    'message' => 'Usuario no existe.',
                    'status' => 404
                );
            }

        }else if ($tipo == 'depositos') {

            if($depositos = Depositos::find($id)){
                $depositos->imagen = $nombre_path;
                $depositos->save();

                $data = array(
                    'depositos' => $depositos,
                    'message' => 'Imágen actualizada correctamente!!!',
                    'status' => 200
                );
            }else{
                $data = array(
                    'message' => 'Usuario no existe.',
                    'status' => 404
                );
            }

        }else if ($tipo == 'compras') {

            if($compras = Compras::find($id)){
                $compras->archivo = $nombre_path;
                $compras->save();

                $data = array(
                    'compras' => $compras,
                    'message' => 'Imágen actualizada correctamente!!!',
                    'status' => 200
                );
            }else{
                $data = array(
                    'message' => 'Usuario no existe.',
                    'status' => 404
                );
            }

        }else if ($tipo == 'usuario') {

            if($usuario = User::find($id)){
                $usuario->imagen = $nombre_path;
                $usuario->save();

                $data = array(
                    'usuario' => $usuario,
                    'message' => 'Imágen actualizada correctamente!!!',
                    'status' => 200
                );
            }else{
                $data = array(
                    'message' => 'Usuario no existe.',
                    'status' => 404
                );
            }

        }else if ($tipo == 'logo') {

            if($datosEmpresa = DatosEmpresa::find($id)){
                $datosEmpresa->logo = $nombre_path;
                $datosEmpresa->save();

                $data = array(
                    'datosEmpresa' => $datosEmpresa,
                    'message' => 'Imágen actualizada correctamente!!!',
                    'status' => 200
                );
            }else{
                $data = array(
                    'message' => 'Usuario no existe.',
                    'status' => 404
                );
            }

        }

        return response()->json($data, $data['status']);
    }

}
