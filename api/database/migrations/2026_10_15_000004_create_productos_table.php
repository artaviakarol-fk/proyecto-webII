<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Shared\Infrastructure\Persistence\RowLevelSecurity;

return new class extends Migration
{
    /** Create the product catalog of each negocio, protected by row-level security. */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 120);
            $table->string('categoria', 60)->nullable();
            $table->decimal('precio_venta', 12, 2);
            $table->decimal('costo_actual', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('es_perecedero')->default(false);
            $table->boolean('es_licor')->default(false);
            $table->string('foto_path', 255)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['negocio_id', 'nombre']);
        });

        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_precio_venta_check CHECK (precio_venta > 0)');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_costo_actual_check CHECK (costo_actual >= 0)');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT productos_stock_check CHECK (stock >= 0)');

        // A code is unique among the live products of a negocio; a deleted product frees its code.
        DB::statement('CREATE UNIQUE INDEX productos_negocio_codigo_unique ON productos (negocio_id, codigo) WHERE deleted_at IS NULL');

        RowLevelSecurity::enable('productos');
    }

    /** Drop the product catalog. */
    public function down(): void
    {
        RowLevelSecurity::disable('productos');
        Schema::dropIfExists('productos');
    }
};
