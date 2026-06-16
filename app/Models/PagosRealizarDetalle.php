<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagosRealizarDetalle extends Model
{
    use HasFactory;
    protected $table = "tbl_pagos_realizar_detalle";

    /**
     * Get the pagos that owns the PagosRealizarDetalle
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pagos()
    {
        return $this->belongsTo(PagosRealizar::class, 'idPagoRealizar');
    }
}
