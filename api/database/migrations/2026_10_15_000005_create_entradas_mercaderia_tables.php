<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Shared\Infrastructure\Persistence\RowLevelSecurity;

return new class extends Migration
{
    /** @var list<string> */
    private const array TABLES = ['entradas_mercaderia', 'entrada_lineas', 'movimientos_inventario'];

    /** Create the goods receipts, their lines and the inventory movements, all protected by row-level security. */
    public function up(): void
    {
        Schema::create('entradas_mercaderia', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('proveedor', 120);
            $table->date('fecha');
            $table->foreignUuid('registrada_por')->constrained('users');
            $table->timestampsTz();

            $table->index(['negocio_id', 'fecha']);
        });

        Schema::create('entrada_lineas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('entrada_id')->constrained('entradas_mercaderia')->cascadeOnDelete();
            $table->foreignUuid('producto_id')->constrained('productos');
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 12, 2);
            $table->timestampsTz();
        });

        // Trazabilidad: cada cambio de stock deja una fila con quién, cuándo, cuánto y el stock que resultó.
        Schema::create('movimientos_inventario', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('producto_id')->constrained('productos');
            $table->string('tipo', 20);
            $table->integer('cantidad');
            $table->integer('stock_resultante');
            $table->uuid('referencia_id')->nullable();
            $table->foreignUuid('usuario_id')->constrained('users');
            $table->timestampsTz();

            $table->index(['negocio_id', 'producto_id', 'created_at']);
        });

        DB::statement('ALTER TABLE entrada_lineas ADD CONSTRAINT entrada_lineas_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE entrada_lineas ADD CONSTRAINT entrada_lineas_costo_check CHECK (costo_unitario >= 0)');
        DB::statement('ALTER TABLE movimientos_inventario ADD CONSTRAINT movimientos_stock_check CHECK (stock_resultante >= 0)');
        DB::statement("ALTER TABLE movimientos_inventario ADD CONSTRAINT movimientos_tipo_check CHECK (tipo IN ('entrada', 'venta', 'anulacion', 'vencimiento', 'ajuste'))");

        foreach (self::TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    /** Drop the goods receipts, their lines and the inventory movements. */
    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            RowLevelSecurity::disable($table);
        }

        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('entrada_lineas');
        Schema::dropIfExists('entradas_mercaderia');
    }
};
