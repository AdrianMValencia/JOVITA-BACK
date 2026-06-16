<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite conservar 10/20/30 al reemitir JSON eFact desde un recibo guardado.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tbl_recibo_detalles')) {
            return;
        }

        if (! Schema::hasColumn('tbl_recibo_detalles', 'codigo_afectacion_igv')) {
            Schema::table('tbl_recibo_detalles', function (Blueprint $table) {
                $table->string('codigo_afectacion_igv', 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbl_recibo_detalles')) {
            return;
        }

        if (Schema::hasColumn('tbl_recibo_detalles', 'codigo_afectacion_igv')) {
            Schema::table('tbl_recibo_detalles', function (Blueprint $table) {
                $table->dropColumn('codigo_afectacion_igv');
            });
        }
    }
};
