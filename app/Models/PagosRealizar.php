<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagosRealizar extends Model
{
    use HasFactory;
    protected $table = "tbl_pagos_realizar";

    /**
     * Get the bancos that owns the PagosRealizar
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bancos()
    {
        return $this->belongsTo(Bancos::class, 'idBanco');
    }

    /**
     * Get the monedas that owns the PagosRealizar
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function monedas()
    {
        return $this->belongsTo(Monedas::class, 'idMoneda');
    }

    /**
     * Get the periodicidad that owns the PagosRealizar
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function periodicidades()
    {
        return $this->belongsTo(Items::class, 'periodicidad', 'codigo')->where('tipo', 'periodicidad');
    }

    /**
     * Get the tipo that owns the PagosRealizar
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipos()
    {
        return $this->belongsTo(Items::class, 'tipo', 'codigo')->where('tipo', 'tipo');
    }

    /**
     * Get all of the detalles for the PagosRealizar
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(PagosRealizarDetalle::class, 'idPagoRealizar');
    }
}
