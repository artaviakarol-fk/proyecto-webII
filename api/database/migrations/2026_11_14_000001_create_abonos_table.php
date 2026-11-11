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
     * Keep every payment (abono) a customer makes on what they owe on fiado, with the balance before and
     * after it, so the receipt can always be printed again. An abono is never changed nor deleted.
     */
    public function up(): void
    {
        Schema::create('abonos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago', 20)->default('efectivo');
            $table->decimal('saldo_anterior', 12, 2);
            $table->decimal('saldo_restante', 12, 2);
            $table->foreignUuid('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['negocio_id', 'cliente_id', 'created_at']);
        });

        // Un abono es positivo, nunca deja el saldo en negativo y lo que queda es lo que había menos el abono.
        // Por ahora se recibe en efectivo; el pago con pasarela agrega sus métodos cuando llegue.
        DB::statement(<<<'SQL'
            ALTER TABLE abonos
                ADD CONSTRAINT abonos_monto_check CHECK (monto > 0),
                ADD CONSTRAINT abonos_saldo_check CHECK (saldo_restante >= 0 AND saldo_restante = saldo_anterior - monto),
                ADD CONSTRAINT abonos_metodo_pago_check CHECK (metodo_pago IN ('efectivo'))
            SQL);

        RowLevelSecurity::enable('abonos');

        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON abonos FROM {$this->applicationRole()}");

        // Las columnas que esperaban a los abonos ya pueden apuntar a ellos.
        Schema::table('movimientos_fiado', function (Blueprint $table): void {
            $table->foreign('abono_id')->references('id')->on('abonos')->restrictOnDelete();
        });
        Schema::table('pagos', function (Blueprint $table): void {
            $table->foreign('abono_id')->references('id')->on('abonos')->restrictOnDelete();
        });
    }

    /** Drop the abonos and the foreign keys that point to them. */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropForeign(['abono_id']);
        });
        Schema::table('movimientos_fiado', function (Blueprint $table): void {
            $table->dropForeign(['abono_id']);
        });

        RowLevelSecurity::disable('abonos');
        Schema::dropIfExists('abonos');
    }

    /** Role the application connects with, quoted as an identifier. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
