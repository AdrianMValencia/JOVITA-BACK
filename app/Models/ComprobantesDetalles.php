<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComprobantesDetalles extends Model
{
    use HasFactory;

    protected $table = 'tbl_facturacion_detalles';

    protected $fillable = [
        'idComprobante',
        'idProducto',
        'cantidad',
        'precioUnitario',
        'subtotal',
        'igv',
        'total',
    ];

    public function comprobante()
    {
        return $this->belongsTo(Comprobantes::class, 'idComprobante');
    }
}
