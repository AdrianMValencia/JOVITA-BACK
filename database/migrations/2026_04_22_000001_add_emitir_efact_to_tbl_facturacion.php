<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tbl_facturacion')) {
            return;
        }

        if (! Schema::hasColumn('tbl_facturacion', 'emitirEfact')) {
            Schema::table('tbl_facturacion', function (Blueprint $table) {
                $table->boolean('emitirEfact')->default(true)->after('idMoneda');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tbl_facturacion')) {
            return;
        }

        if (Schema::hasColumn('tbl_facturacion', 'emitirEfact')) {
            Schema::table('tbl_facturacion', function (Blueprint $table) {
                $table->dropColumn('emitirEfact');
            });
        }
    }
};
