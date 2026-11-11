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
     * Create the customers who buy on credit (fiado). The application can register and edit them,
     * but it can never write the balance: only the movements of fiado change it.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('telefono', 20)->nullable();
            $table->decimal('limite_fiado', 12, 2);
            $table->decimal('saldo_actual', 12, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();

            $table->index(['negocio_id', 'nombre']);
        });

        // El límite y el saldo nunca son negativos: un abono no puede dejar al cliente a favor.
        DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_limite_fiado_check CHECK (limite_fiado >= 0)');
        DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_saldo_actual_check CHECK (saldo_actual >= 0)');

        RowLevelSecurity::enable('clientes');

        // La aplicación no escribe saldo_actual ni borra clientes: se desactivan para conservar su historial.
        $role = $this->applicationRole();
        DB::statement("REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON clientes FROM {$role}");
        DB::statement("GRANT INSERT (id, negocio_id, nombre, telefono, limite_fiado, activo, created_at, updated_at) ON clientes TO {$role}");
        DB::statement("GRANT UPDATE (nombre, telefono, limite_fiado, activo, updated_at) ON clientes TO {$role}");
    }

    /** Drop the customers. */
    public function down(): void
    {
        RowLevelSecurity::disable('clientes');
        Schema::dropIfExists('clientes');
    }

    /** Role the application connects with, quoted as an identifier. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
