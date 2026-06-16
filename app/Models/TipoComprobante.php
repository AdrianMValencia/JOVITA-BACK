<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    use HasFactory;

    protected $table = 'tbl_comprobantes';

    // si la tabla tiene timestamps, Laravel los manejará por defecto; si no puede deshabilitarlos:
    // public $timestamps = false;

    // relación inversa si se necesita
    public function comprobantes()
    {
        return $this->hasMany(Comprobantes::class, 'idTipoComprobante');
    }
}
