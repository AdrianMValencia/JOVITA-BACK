<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolesModuloOperacion extends Model
{
    use HasFactory;
    protected $table = 'tbl_rol_modulo_operacion';

    public function roles(){
    	return $this->belongsTo('App\Models\Roles', 'idRol');
    }

    public function subModulos(){
    	return $this->belongsTo('App\Models\Modulo', 'idSubModulo');
    }
}
