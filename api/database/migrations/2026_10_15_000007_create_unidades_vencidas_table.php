<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Shared\Infrastructure\Persistence\RowLevelSecurity;

return new class extends Migration
{
    /**
     * Units found expired on the shelf. While "activa", they stay in the stock but are not sellable;
     * #13 adds the endpoint that marks them and retires them when they go back to the supplier.
     */
    public function up(): void
    {
        Schema::create('unidades_vencidas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('producto_id')->constrained('productos');
            $table->integer('cantidad');
            $table->string('motivo', 200);
            $table->string('estado', 20)->default('activa');
            $table->foreignUuid('usuario_id')->constrained('users');
            $table->timestampsTz();

            $table->index(['negocio_id', 'producto_id', 'estado']);
        });

        DB::statement('ALTER TABLE unidades_vencidas ADD CONSTRAINT unidades_vencidas_cantidad_check CHECK (cantidad > 0)');
        DB::statement("ALTER TABLE unidades_vencidas ADD CONSTRAINT unidades_vencidas_estado_check CHECK (estado IN ('activa', 'retirada'))");

        RowLevelSecurity::enable('unidades_vencidas');
    }

    /** Drop the expired units. */
    public function down(): void
    {
        RowLevelSecurity::disable('unidades_vencidas');
        Schema::dropIfExists('unidades_vencidas');
    }
};
