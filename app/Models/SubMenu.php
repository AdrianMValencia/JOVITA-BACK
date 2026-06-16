<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubMenu extends Model
{
    protected $table = 'submenu';

    public function menu(){
    	return $this->belongsTo('App\Models\Menu', 'menuId');
    }
}
