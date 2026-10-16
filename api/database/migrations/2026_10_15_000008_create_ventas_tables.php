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
    private const array TABLES = ['ventas', 'venta_lineas'];

    /** Create the sales and their lines, both protected by row-level security. */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('cajero_id')->constrained('users');
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->string('estado', 20)->default('abierta');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestampTz('cobrada_at')->nullable();
            $table->timestampTz('anulada_at')->nullable();
            $table->timestampsTz();

            $table->index(['negocio_id', 'estado', 'created_at']);
        });

        Schema::create('venta_lineas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignUuid('producto_id')->constrained('productos');
            $table->smallInteger('posicion');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestampsTz();

            $table->index(['venta_id', 'posicion']);
        });

        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_estado_check CHECK (estado IN ('abierta', 'cobrada', 'anulada'))");
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_metodo_pago_check CHECK (metodo_pago IN ('efectivo'))");
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT ventas_total_check CHECK (total >= 0)');
        DB::statement('ALTER TABLE venta_lineas ADD CONSTRAINT venta_lineas_cantidad_check CHECK (cantidad > 0)');
        DB::statement('ALTER TABLE venta_lineas ADD CONSTRAINT venta_lineas_precio_check CHECK (precio_unitario > 0)');

        foreach (self::TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    /** Drop the sales and their lines. */
    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            RowLevelSecurity::disable($table);
        }

        Schema::dropIfExists('venta_lineas');
        Schema::dropIfExists('ventas');
    }
};
