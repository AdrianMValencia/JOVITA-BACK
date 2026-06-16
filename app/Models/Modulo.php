<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    use HasFactory;

    protected $table = "tbl_modulo";

    public function subModulos()
    {
        return $this->hasMany('App\Models\ModuloSub', 'idModulo');
    }
}
