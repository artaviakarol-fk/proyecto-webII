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
     * Units a cliente holds for a while before paying. While a reserva is "activa" and has not expired, its units
     * stay in the stock but nobody else can reserve or buy them. Nothing has to run when the time is over: an
     * expired reserva simply stops counting, because every reading compares `expira_en` with the current time.
     */
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('producto_id')->constrained('productos');
            $table->foreignUuid('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->integer('cantidad');
            $table->string('estado', 20)->default('activa');
            $table->timestampTz('expira_en');
            $table->timestampsTz();

            $table->index(['negocio_id', 'producto_id', 'estado', 'expira_en']);
            $table->index(['negocio_id', 'usuario_id', 'estado']);
        });

        DB::statement('ALTER TABLE reservas ADD CONSTRAINT reservas_cantidad_check CHECK (cantidad > 0)');
        DB::statement("ALTER TABLE reservas ADD CONSTRAINT reservas_estado_check CHECK (estado IN ('activa', 'cancelada', 'confirmada'))");
        // A cliente holds each product at most once: reserving it again replaces the previous reserva.
        DB::statement("CREATE UNIQUE INDEX reservas_una_activa_por_producto ON reservas (usuario_id, producto_id) WHERE estado = 'activa'");

        RowLevelSecurity::enable('reservas');
    }

    /** Drop the reservas. */
    public function down(): void
    {
        RowLevelSecurity::disable('reservas');
        Schema::dropIfExists('reservas');
    }
};
