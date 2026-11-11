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
     * Keep every notification of a provider that was already processed. The pair provider and event id is unique, so a
     * notification that the provider sends again is recognised and does not charge twice. The table belongs to the
     * negocio of the notification and is protected by row-level security like the others.
     */
    public function up(): void
    {
        Schema::create('webhook_eventos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('proveedor', 20);
            $table->string('evento_id', 255);
            $table->string('tipo', 100)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('procesado_at');
            $table->timestampsTz();

            $table->unique(['proveedor', 'evento_id']);
            $table->index(['negocio_id', 'procesado_at']);
        });

        DB::statement("ALTER TABLE webhook_eventos ADD CONSTRAINT webhook_eventos_proveedor_check CHECK (proveedor IN ('onvo', 'paypal'))");

        RowLevelSecurity::enable('webhook_eventos');
    }

    /** Drop the table of the notifications. */
    public function down(): void
    {
        RowLevelSecurity::disable('webhook_eventos');

        Schema::dropIfExists('webhook_eventos');
    }
};
