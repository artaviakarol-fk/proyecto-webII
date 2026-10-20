<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Shared\Infrastructure\Persistence\RowLevelSecurity;

return new class extends Migration
{
    /** Create the weekly opening hours of each negocio: one row per slot, protected by row-level security. */
    public function up(): void
    {
        Schema::create('horarios_negocio', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->unsignedSmallInteger('dia_semana');
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->timestampsTz();

            $table->index(['negocio_id', 'dia_semana']);
        });

        // 1 = lunes ... 7 = domingo; un tramo siempre cierra después de abrir.
        DB::statement('ALTER TABLE horarios_negocio ADD CONSTRAINT horarios_negocio_dia_check CHECK (dia_semana BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE horarios_negocio ADD CONSTRAINT horarios_negocio_tramo_check CHECK (hora_cierre > hora_apertura)');

        RowLevelSecurity::enable('horarios_negocio');
    }

    /** Drop the opening hours. */
    public function down(): void
    {
        RowLevelSecurity::disable('horarios_negocio');
        Schema::dropIfExists('horarios_negocio');
    }
};
