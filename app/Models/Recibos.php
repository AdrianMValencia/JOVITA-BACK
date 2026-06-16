<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Recibos extends Model
{
    use HasFactory;
    protected $table = "tbl_recibos";

    /**
     * Get the puntoventa that owns the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function puntoventa()
    {
        return $this->belongsTo(PuntoVenta::class, 'idPuntoVenta');
    }

    /**
     * Get the clientes that owns the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function clientes()
    {
        return $this->belongsTo(Clientes::class, 'idCliente');
    }

    /**
     * Get the monedas that owns the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function monedas()
    {
        return $this->belongsTo(Monedas::class, 'idMoneda');
    }

    /**
     * Get the series that owns the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function seriesList()
    {
        return $this->belongsTo(Series::class, 'idSeries');
    }

    /**
     * Get the usuarios that owns the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuarios()
    {
        return $this->belongsTo(User::class, 'idUsuario');
    }

    /**
     * Get all of the detalles for the Recibos
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detalles()
    {
        return $this->hasMany(RecibosDetalles::class, 'idRecibo');
    }

    /**
     * Mayor correlativo POS numérico para un punto de venta y serie de ticket.
     * Si `numeracion` es VARCHAR, MAX(numeracion) en SQL sería lexicográfico ("99" > "198"); se usa CAST numérico.
     */
    public static function maxNumeracionParaSeriePuntoVenta(int $idPuntoVenta, string $serie): int
    {
        $serie = trim($serie);
        if ($serie === '' || $idPuntoVenta < 1) {
            return 0;
        }

        $v = static::query()
            ->where('idPuntoVenta', $idPuntoVenta)
            ->where(function ($q) use ($serie) {
                $q->where('series', $serie)->orWhereRaw('TRIM(CAST(series AS CHAR)) = ?', [$serie]);
            })
            ->max(DB::raw('CAST(`numeracion` AS UNSIGNED)'));

        return max(0, (int) ($v ?? 0));
    }
}
