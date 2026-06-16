<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprasDetalles extends Model
{
    use HasFactory;
    protected $table = "tbl_compras_detalle";

    /**
     * Get the compras that owns the ComprasDetalles
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function compras()
    {
        return $this->belongsTo(Compras::class, 'idCompra');
    }

    /**
     * Get the productos that owns the ComprasDetalles
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productos(): BelongsTo
    {
        return $this->belongsTo(Productos::class, 'idProducto');
    }
}
