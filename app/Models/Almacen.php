<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    use HasFactory;
    protected $table = "tbl_almacen";

    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }
    /**
     * Get the ubigeos that owns the Almacen
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ubigeos()
    {
        return $this->belongsTo(Ubigeo::class, 'idUbigeo');
    }
}
