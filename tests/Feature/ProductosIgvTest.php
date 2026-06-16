<?php

namespace Tests\Feature;

use App\Models\Productos;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductosIgvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        Schema::create('tbl_productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->boolean('igv')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tbl_productos');
        parent::tearDown();
    }

    public function test_create_product_with_igv_true_and_false(): void
    {
        $productoTrue = new Productos();
        $productoTrue->nombre = 'Producto IGV true';
        $productoTrue->igv = true;
        $productoTrue->save();

        $productoFalse = new Productos();
        $productoFalse->nombre = 'Producto IGV false';
        $productoFalse->igv = false;
        $productoFalse->save();

        $this->assertTrue($productoTrue->fresh()->igv);
        $this->assertFalse($productoFalse->fresh()->igv);
    }

    public function test_update_product_igv_value(): void
    {
        $producto = new Productos();
        $producto->nombre = 'Producto para update';
        $producto->igv = true;
        $producto->save();

        $producto->igv = false;
        $producto->save();

        $this->assertFalse($producto->fresh()->igv);
    }

    public function test_create_product_without_igv_uses_default_true(): void
    {
        $producto = new Productos();
        $producto->nombre = 'Producto sin igv';
        $producto->save();

        $this->assertTrue($producto->fresh()->igv);
    }
}
