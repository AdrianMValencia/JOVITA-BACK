<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comprobantes extends Model
{
    use HasFactory;

    protected $table = 'tbl_facturacion';

    // permitir asignación masiva de los campos comunes más los nuevos agregados
    protected $fillable = [
        'idPuntoVenta',
        'puntoVenta',
        'idSerie',
        'serie',
        'idNumeracion',
        'numeracion',
        'idTipoComprobante',
        'tipo',
        'fecha',
        'numero',
        'cliente',
        'direccion',
        'celular',
        'correo',
        // campos recientemente añadidos a la tabla
        'codigo',
        'total',
        'idTipoCambio',
        'tipoCambio',
        'igv',
        'subTotal',
        'idMoneda',
        'emitirEfact',
        // OSE eFact
        'efact_ticket',
        'efact_estado',
        'efact_comprobante_serie',
        'efact_comprobante_numero',
    ];

    // convertir tipos cuando se recuperan
    protected $casts = [
        'total' => 'float',
        'tipoCambio' => 'float',
        'igv' => 'float',
        'subTotal' => 'float',
        'fecha' => 'date',
    ];

    public function tipo()
    {
        return $this->belongsTo(TipoComprobante::class, 'idTipoComprobante');
    }

    public function detalles()
    {
        return $this->hasMany(ComprobantesDetalles::class, 'idComprobante');
    }
}
