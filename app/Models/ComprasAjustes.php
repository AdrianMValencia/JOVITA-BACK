<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprasAjustes extends Model
{
    use HasFactory;
    protected $table = "tbl_compras_ajustes";

    /**
     * Get the compras that owns the ComprasAjustes
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function compras(): BelongsTo
    {
        return $this->belongsTo(Compras::class, 'idCompra');
    }

    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }

    /**
     * Get the productos that owns the ProductoAjustes
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productos()
    {
        return $this->belongsTo(Productos::class, 'idProducto');
    }
}
