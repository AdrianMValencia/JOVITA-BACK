<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActualizacionInventarios extends Model
{
    use HasFactory;
    protected $table = "tbl_actualizacion_inventarios";

    /**
     * Get all of the detalles for the ActualizacionInventarios
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(ActualizacionInventariosDetalles::class, 'idInventario');
    }
}
