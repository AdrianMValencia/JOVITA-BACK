<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    use HasFactory;
    protected $table = "tbl_cotizacion";

    /**
     * Get the puntoventa that owns the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }

    /**
     * Get the clientes that owns the Cotizacion
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function clientes()
    {
        return $this->belongsTo(Clientes::class, 'idCliente');
    }

    /**
     * Get all of the detalles for the Cotizacion
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(CotizacionDetalles::class, 'idCotizacion');
    }
}
