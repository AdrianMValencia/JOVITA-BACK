<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Abastecimientos extends Model
{
    use HasFactory;
    protected $table = "tbl_abastecimientos";

    /**
     * Get all of the detalles for the Abastecimientos
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(AbastecimientoDetalles::class, 'idAbastecimiento');
    }
}
