<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compras extends Model
{
    use HasFactory;
    protected $table = "tbl_compras";

    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }

    /**
     * Get the proveedores that owns the Compras
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function proveedores()
    {
        return $this->belongsTo(Proveedores::class, 'idProveedor');
    }

    /**
     * Get the comprobantes that owns the Compras
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function comprobantes()
    {
        return $this->belongsTo(TipoDocumento::class, 'idTipoDocumento');
    }

    /**
     * Get all of the detalles for the Compras
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(ComprasDetalles::class, 'idCompra');
    }
}
