<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cajas extends Model
{
    use HasFactory;
    protected $table = "tbl_cajas";

    /**
     * Get the series that owns the Cajas
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function series()
    {
        return $this->belongsTo(SeriesTickets::class, 'idSerieTicket');
    }

    /**
     * Get the puntoventa that owns the Productos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }
}
