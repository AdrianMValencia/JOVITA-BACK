<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Productos extends Model
{
    use HasFactory;
    protected $table = "tbl_productos";

    protected $casts = [
        'igv' => 'boolean',
    ];

    /**
     * Get the categorias that owns the Productos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function categorias()
    {
        return $this->belongsTo(Categorias::class, 'idCategoria');
    }

    /**
     * Get the um that owns the Productos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function um()
    {
        return $this->belongsTo(UnidadMedida::class, 'idUm');
    }

    /**
     * Get the puntoventa that owns the Productos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }

    /**
     * The proveedores that belong to the Productos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function proveedores()
    {
        return $this->belongsToMany(Proveedores::class, 'tbl_productos_proveedores', 'idProducto', 'idProveedor')
                     ->where('tbl_proveedor.status', 1);
    }
}
