<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Serie y correlativo del comprobante electrónico SUNAT (p. ej. BE01 + 00000001),
     * separados de la serie/numeración del ticket POS en tbl_recibos.
     */
    public function up(): void
    {
        if (Schema::hasTable('tbl_recibos')) {
            if (! Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie')) {
                Schema::table('tbl_recibos', function (Blueprint $table) {
                    $table->string('efact_comprobante_serie', 16)->nullable();
                });
            }
            if (! Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero')) {
                Schema::table('tbl_recibos', function (Blueprint $table) {
                    $table->string('efact_comprobante_numero', 16)->nullable();
                });
            }
        }

        if (Schema::hasTable('tbl_facturacion')) {
            if (! Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                Schema::table('tbl_facturacion', function (Blueprint $table) {
                    $table->string('efact_comprobante_serie', 16)->nullable();
                });
            }
            if (! Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
                Schema::table('tbl_facturacion', function (Blueprint $table) {
                    $table->string('efact_comprobante_numero', 16)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_recibos')) {
            if (Schema::hasColumn('tbl_recibos', 'efact_comprobante_numero')) {
                Schema::table('tbl_recibos', function (Blueprint $table) {
                    $table->dropColumn('efact_comprobante_numero');
                });
            }
            if (Schema::hasColumn('tbl_recibos', 'efact_comprobante_serie')) {
                Schema::table('tbl_recibos', function (Blueprint $table) {
                    $table->dropColumn('efact_comprobante_serie');
                });
            }
        }

        if (Schema::hasTable('tbl_facturacion')) {
            if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_numero')) {
                Schema::table('tbl_facturacion', function (Blueprint $table) {
                    $table->dropColumn('efact_comprobante_numero');
                });
            }
            if (Schema::hasColumn('tbl_facturacion', 'efact_comprobante_serie')) {
                Schema::table('tbl_facturacion', function (Blueprint $table) {
                    $table->dropColumn('efact_comprobante_serie');
                });
            }
        }
    }
};
