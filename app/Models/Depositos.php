<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Depositos extends Model
{
    use HasFactory;
    protected $table = "tbl_depositos";

    /**
     * Get the bancos that owns the Depositos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bancos()
    {
        return $this->belongsTo(Bancos::class, 'idBanco');
    }
}
