<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clientes extends Model
{
    use HasFactory;
    protected $table = "tbl_clientes";

    /**
     * Get the tipoDoi that owns the Clientes
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipodoi()
    {
        return $this->belongsTo(TipoDoi::class, 'idTipoDoi');
    }

    /**
     * Get the ubigeo that owns the Clientes
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ubigeos()
    {
        return $this->belongsTo(Ubigeo::class, 'idUbigeo');
    }

    /**
     * Get the puntoventa that owns the Clientes
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }
}
