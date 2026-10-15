<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Shared\Infrastructure\Persistence\RowLevelSecurity;

return new class extends Migration
{
    /** Keep the history of cost and sale price of each product, and the previous cost of each receipt line. */
    public function up(): void
    {
        Schema::create('historial_precios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('producto_id')->constrained('productos');
            $table->decimal('costo', 12, 2);
            $table->decimal('precio_venta', 12, 2);
            $table->string('motivo', 20);
            $table->uuid('referencia_id')->nullable();
            $table->foreignUuid('usuario_id')->constrained('users');
            $table->timestampsTz();

            $table->index(['negocio_id', 'producto_id', 'created_at']);
        });

        DB::statement("ALTER TABLE historial_precios ADD CONSTRAINT historial_precios_motivo_check CHECK (motivo IN ('creacion', 'edicion', 'entrada', 'cambio_precio'))");
        DB::statement('ALTER TABLE historial_precios ADD CONSTRAINT historial_precios_precio_check CHECK (precio_venta > 0 AND costo >= 0)');

        RowLevelSecurity::enable('historial_precios');

        Schema::table('entrada_lineas', function (Blueprint $table): void {
            $table->decimal('costo_anterior', 12, 2)->nullable()->after('costo_unitario');
        });
    }

    /** Drop the price history and the previous cost of the receipt lines. */
    public function down(): void
    {
        Schema::table('entrada_lineas', function (Blueprint $table): void {
            $table->dropColumn('costo_anterior');
        });

        RowLevelSecurity::disable('historial_precios');
        Schema::dropIfExists('historial_precios');
    }
};
