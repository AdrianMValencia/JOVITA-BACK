<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EfactLog extends Model
{
    protected $table = 'tbl_efact_logs';

    protected $fillable = [
        'idComprobante',
        'ticket',
        'tipo_operacion',
        'response_json',
        'status_code',
    ];
}
