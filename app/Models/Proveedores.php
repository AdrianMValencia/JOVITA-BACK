<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedores extends Model
{
    use HasFactory;
    protected $table = "tbl_proveedor";

    /**
     * Get the puntoVenta that owns the Proveedores
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }

    /**
     * Get the tipodoi that owns the Proveedores
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipodoi()
    {
        return $this->belongsTo(TipoDoi::class, 'idTipoDoi');
    }

    /**
     * Get the ubigeos that owns the Proveedores
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ubigeos()
    {
        return $this->belongsTo(Ubigeo::class, 'idUbigeo');
    }

    public function productos()
    {
        return $this->belongsToMany(Productos::class, 'tbl_productos_proveedores', 'idProveedor', 'idProducto')
                    ->where('tbl_productos.status', 1); // Ajusta si tu tabla no se llama 'productos'
    }
}
