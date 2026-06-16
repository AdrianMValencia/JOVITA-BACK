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
        if (! Schema::hasTable('tbl_recibos')) {
            return;
        }

        if (! Schema::hasColumn('tbl_recibos', 'emitirEfact')) {
            Schema::table('tbl_recibos', function (Blueprint $table) {
                $table->boolean('emitirEfact')->default(false)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tbl_recibos')) {
            return;
        }

        if (Schema::hasColumn('tbl_recibos', 'emitirEfact')) {
            Schema::table('tbl_recibos', function (Blueprint $table) {
                $table->dropColumn('emitirEfact');
            });
        }
    }
};
