<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuloSub extends Model
{
    use HasFactory;

    protected $table = "tbl_modulo_sub";

    public function modulos(){
        return $this->belongsTo('App\Models\Modulo', 'idModulo');
    }

    public function permisos(){
        return $this->hasMany('App\Models\RolesModuloOperacion', 'idSubModulo');
    }
}
