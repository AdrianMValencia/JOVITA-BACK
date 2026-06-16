<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ubicaciones extends Model
{
    use HasFactory;
    protected $table = "tbl_ubicaciones";

    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }

    public function productos()
    {
        return $this->belongsTo(Productos::class, 'idProducto');
    }

    /**
     * Get the almacen that owns the Ubicaciones
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function almacenes()
    {
        return $this->belongsTo(User::class, 'idAlmacen');
    }
}
