<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedidos extends Model
{
    use HasFactory;
    protected $table = "tbl_pedidos";

    /**
     * The detalles that belong to the Pedidos
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(PedidosDetalles::class, 'idPedido');
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
