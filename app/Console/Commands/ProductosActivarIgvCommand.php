<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductosActivarIgvCommand extends Command
{
    protected $signature = 'productos:activar-igv
                            {--id-punto-venta= : Solo productos de este punto de venta}
                            {--dry-run : Muestra cuántos registros se actualizarían sin guardar}
                            {--force : Ejecuta sin pedir confirmación}';

    protected $description = 'Marca todos los productos como afectos a IGV (tbl_productos.igv = 1)';

    public function handle(): int
    {
        if (! Schema::hasTable('tbl_productos')) {
            $this->error('No existe la tabla tbl_productos.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('tbl_productos', 'igv')) {
            $this->error('La columna igv no existe. Ejecute: php artisan migrate');

            return self::FAILURE;
        }

        $idPv = $this->option('id-punto-venta');
        $idPvInt = ($idPv !== null && $idPv !== '') ? (int) $idPv : null;

        $query = DB::table('tbl_productos');
        if ($idPvInt !== null && $idPvInt > 0) {
            $query->where('idPuntoVenta', $idPvInt);
            $this->info("Filtro: idPuntoVenta = {$idPvInt}");
        }

        $total = (clone $query)->count();
        $sinIgv = (clone $query)->where(function ($q) {
            $q->where('igv', 0)
                ->orWhereNull('igv');
        })->count();

        $this->line("Productos en alcance: {$total}");
        $this->line("Sin IGV actualmente (0 o NULL): {$sinIgv}");

        if ($this->option('dry-run')) {
            $this->warn('Modo dry-run: no se guardó ningún cambio.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Actualizar TODOS los productos del alcance a afectos a IGV (igv = 1)?', true)) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        $actualizados = $query->update(['igv' => 1]);

        $this->info("Listo. Registros actualizados: {$actualizados}");

        return self::SUCCESS;
    }
}
