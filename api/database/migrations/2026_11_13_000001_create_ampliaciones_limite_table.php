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
     * Keep every exceptional increase of the limit of fiado that a cashier gives: who, when, how much,
     * why and until when. An increase is never deleted; the administration reverts it, and that is recorded too.
     */
    public function up(): void
    {
        Schema::create('ampliaciones_limite', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 255);
            $table->foreignUuid('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('vigente_hasta');
            $table->timestampTz('revertida_at')->nullable();
            $table->foreignUuid('revertida_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['negocio_id', 'cliente_id', 'vigente_hasta']);
            $table->index(['negocio_id', 'created_at']);
        });

        // Una ampliación suma, tiene motivo, vence después de darse y, si se revierte, se sabe quién y cuándo.
        DB::statement(<<<'SQL'
            ALTER TABLE ampliaciones_limite
                ADD CONSTRAINT ampliaciones_limite_monto_check CHECK (monto > 0),
                ADD CONSTRAINT ampliaciones_limite_motivo_check CHECK (length(trim(motivo)) > 0),
                ADD CONSTRAINT ampliaciones_limite_vigencia_check CHECK (vigente_hasta > created_at),
                ADD CONSTRAINT ampliaciones_limite_reversion_check CHECK ((revertida_at IS NULL) = (revertida_por IS NULL))
            SQL);

        RowLevelSecurity::enable('ampliaciones_limite');

        // La auditoría no se borra: la aplicación puede registrar ampliaciones y revertirlas, nada más.
        DB::statement("REVOKE DELETE, TRUNCATE ON ampliaciones_limite FROM {$this->applicationRole()}");
    }

    /** Drop the increases. */
    public function down(): void
    {
        RowLevelSecurity::disable('ampliaciones_limite');
        Schema::dropIfExists('ampliaciones_limite');
    }

    /** Role the application connects with, quoted as an identifier. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
