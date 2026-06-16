<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActualizacionInventariosDetalles extends Model
{
    use HasFactory;
    protected $table = "tbl_actualizacion_inventarios_detalles";

    public function inventarios()
    {
        return $this->hasMany(ActualizacionInventarios::class, 'idInventario');
    }

    public function categorias()
    {
        return $this->hasMany(categorias::class, 'idCategoria');
    }

    public function productos()
    {
        return $this->hasMany(Productos::class, 'idProducto');
    }
}
