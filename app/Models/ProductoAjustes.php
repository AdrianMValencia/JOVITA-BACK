<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoAjustes extends Model
{
    use HasFactory;
    protected $table = "tbl_producto_ajustes";

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
