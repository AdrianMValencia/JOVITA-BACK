<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiposCambio extends Model
{
    use HasFactory;
    protected $table = "tbl_tipo_cambio";

    /**
     * Get the monedas that owns the TiposCambio
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function monedas()
    {
        return $this->belongsTo(Monedas::class, 'idMoneda');
    }
}
