<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecibosMonedas extends Model
{
    use HasFactory;
    protected $table = "tbl_recibo_moneda";

    /**
     * Get the recibos that owns the RecibosMonedas
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recibos()
    {
        return $this->belongsTo(Recibos::class, 'idRecibo');
    }

    /**
     * Get the monedas that owns the RecibosMonedas
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function monedas()
    {
        return $this->belongsTo(Monedas::class, 'idMoneda');
    }
}
