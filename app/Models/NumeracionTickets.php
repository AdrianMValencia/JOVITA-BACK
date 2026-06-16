<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumeracionTickets extends Model
{
    use HasFactory;
    protected $table = "tbl_numeracion_tickets";

    /**
     * Get the series that owns the NumeracionTickets
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function series()
    {
        return $this->belongsTo(SeriesTickets::class, 'idSeriesTickets');
    }
}
