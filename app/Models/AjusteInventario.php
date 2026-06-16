<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AjusteInventario extends Model
{
    use HasFactory;
    protected $table = "tbl_ajuste_inventario";

    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }
}
