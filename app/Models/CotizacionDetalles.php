<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionDetalles extends Model
{
    use HasFactory;
    protected $table = "tbl_cotizacion_detalles";

    /**
     * Get the cotizaciones that owns the CotizacionDetalles
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cotizaciones()
    {
        return $this->belongsTo(Cotizacion::class, 'idCotizacion');
    }

    /**
     * Get the productos that owns the CotizacionDetalles
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productos()
    {
        return $this->belongsTo(Productos::class, 'idProducto');
    }
}
