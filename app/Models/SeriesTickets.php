<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeriesTickets extends Model
{
    use HasFactory;
    protected $table = "tbl_series_tickets";

   /**
    * Get the puntoVenta that owns the SeriesTickets
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
   public function puntoventa()
   {
       return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
   }

   /**
    * Get all of the numeracion for the SeriesTickets
    *
    * @return \Illuminate\Database\Eloquent\Relations\HasMany
    */
   public function numeracion()
   {
       return $this->hasMany(NumeracionTickets::class, 'idSeriesTickets');
   }
}
