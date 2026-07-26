<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Serie del documento de compra (p. ej. "F001" en una factura de proveedor "F001-123"),
     * separada del número de documento, para que el reporte RCE SIRE pueda listarla en su
     * propia columna en vez de tener que parsearla desde `numeroTipoDocumento`.
     */
    public function up(): void
    {
        if (Schema::hasTable('tbl_compras') && ! Schema::hasColumn('tbl_compras', 'serieTipoDocumento')) {
            Schema::table('tbl_compras', function (Blueprint $table) {
                $table->string('serieTipoDocumento', 20)->nullable()->after('nombreTipoDocumento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_compras') && Schema::hasColumn('tbl_compras', 'serieTipoDocumento')) {
            Schema::table('tbl_compras', function (Blueprint $table) {
                $table->dropColumn('serieTipoDocumento');
            });
        }
    }
};
