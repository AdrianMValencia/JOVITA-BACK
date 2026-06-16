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
        if (!Schema::hasTable('tbl_productos')) {
            return;
        }

        Schema::table('tbl_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_productos', 'igv')) {
                $table->boolean('igv')->default(true)->after('descuento');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('tbl_productos')) {
            return;
        }

        Schema::table('tbl_productos', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_productos', 'igv')) {
                $table->dropColumn('igv');
            }
        });
    }
};
