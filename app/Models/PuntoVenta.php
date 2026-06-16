<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PuntoVenta extends Model
{
    use HasFactory;
    protected $table = "tbl_punto_venta";

    /**
     * Get the ubigeos that owns the PuntoVenta
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ubigeos()
    {
        return $this->belongsTo(Ubigeo::class, 'idUbigeo');
    }

    /**
     * Get all of the series for the PuntoVenta
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function series()
    {
        return $this->hasMany(SeriesTickets::class, 'idPuntoVenta');
    }
}
