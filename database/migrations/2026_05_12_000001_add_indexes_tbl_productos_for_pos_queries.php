<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mejora consultas del POS: listado por punto de venta + estado, y búsqueda por código de barras.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tbl_productos')) {
            return;
        }

        Schema::table('tbl_productos', function (Blueprint $table) {
            $table->index(['idPuntoVenta', 'status'], 'idx_tbl_productos_pv_status');
        });

        Schema::table('tbl_productos', function (Blueprint $table) {
            $table->index(['idPuntoVenta', 'codigoBarra', 'status'], 'idx_tbl_productos_pv_codigo_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbl_productos')) {
            return;
        }

        Schema::table('tbl_productos', function (Blueprint $table) {
            $table->dropIndex('idx_tbl_productos_pv_status');
        });

        Schema::table('tbl_productos', function (Blueprint $table) {
            $table->dropIndex('idx_tbl_productos_pv_codigo_status');
        });
    }
};
