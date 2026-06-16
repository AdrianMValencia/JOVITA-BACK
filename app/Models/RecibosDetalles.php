<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecibosDetalles extends Model
{
    use HasFactory;
    protected $table = "tbl_recibo_detalles";

    /**
     * Get the recibos that owns the RecibosDetalles
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recibos()
    {
        return $this->belongsTo(Recibos::class, 'idRecibo');
    }

    /**
     * Get the productos that owns the RecibosDetalles
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function productos()
    {
        return $this->belongsTo(Productos::class, 'idProducto');
    }
}
