<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenRequerimiento extends Model
{
    use HasFactory;
    protected $table = "tbl_orden_requerimiento";

    /**
     * The detalles that belong to the Pedidos
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(OrdenRequerimientoDetalles::class, 'idOrdenRequerimiento');
    }

        /**
     * Get the puntoventa that owns the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventas()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }
}
