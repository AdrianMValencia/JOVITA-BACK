<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentasTotales extends Model
{
    use HasFactory;
    protected $table = "tbl_ventas_totales";

    /**
     * Get the puntoventa that owns the VentasTotales
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventas()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }
}
