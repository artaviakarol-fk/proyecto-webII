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
     * Keep the fiado of each customer as a ledger: a charge (a sale on credit) adds to the balance
     * and a payment (abono) takes from it. Rows are only added, and each one moves the balance
     * of its customer in the same statement, so saldo_actual is always the sum of the ledger.
     */
    public function up(): void
    {
        Schema::create('movimientos_fiado', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->decimal('monto', 12, 2);
            $table->foreignUuid('venta_id')->nullable()->constrained('ventas')->restrictOnDelete();
            // La tabla de abonos llega con la US-305; por ahora solo se guarda el identificador.
            $table->uuid('abono_id')->nullable();
            $table->foreignUuid('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['negocio_id', 'cliente_id', 'created_at']);
        });

        // Un cargo viene de una venta y un abono de un abono; el monto siempre es positivo.
        DB::statement(<<<'SQL'
            ALTER TABLE movimientos_fiado
                ADD CONSTRAINT movimientos_fiado_tipo_check CHECK (
                    (tipo = 'cargo' AND venta_id IS NOT NULL AND abono_id IS NULL)
                    OR (tipo = 'abono' AND abono_id IS NOT NULL AND venta_id IS NULL)
                ),
                ADD CONSTRAINT movimientos_fiado_monto_check CHECK (monto > 0)
            SQL);

        // Una venta se carga al fiado una sola vez, y un abono se registra una sola vez.
        DB::statement("CREATE UNIQUE INDEX movimientos_fiado_venta_unique ON movimientos_fiado (venta_id) WHERE tipo = 'cargo'");
        DB::statement("CREATE UNIQUE INDEX movimientos_fiado_abono_unique ON movimientos_fiado (abono_id) WHERE tipo = 'abono'");

        RowLevelSecurity::enable('movimientos_fiado');

        DB::statement("REVOKE UPDATE, DELETE, TRUNCATE ON movimientos_fiado FROM {$this->applicationRole()}");

        DB::unprepared(<<<'SQL'
            -- Mueve el saldo del cliente con cada movimiento. Corre con los permisos del dueño del esquema,
            -- porque la aplicación no puede escribir saldo_actual; la política de cada negocio sigue aplicando.
            CREATE OR REPLACE FUNCTION fn_aplicar_movimiento_fiado() RETURNS trigger
                LANGUAGE plpgsql
                SECURITY DEFINER
                SET search_path = public
            AS $$
            BEGIN
                UPDATE clientes
                   SET saldo_actual = saldo_actual + CASE NEW.tipo WHEN 'cargo' THEN NEW.monto ELSE -NEW.monto END,
                       updated_at = now()
                 WHERE id = NEW.cliente_id
                   AND negocio_id = NEW.negocio_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'El cliente % no pertenece al negocio del movimiento.', NEW.cliente_id
                        USING ERRCODE = 'foreign_key_violation';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER trg_movimientos_fiado_aplicar
                AFTER INSERT ON movimientos_fiado
                FOR EACH ROW
                EXECUTE FUNCTION fn_aplicar_movimiento_fiado();

            -- Ni siquiera el dueño del esquema cambia o borra un movimiento: se corrige con otro.
            CREATE OR REPLACE FUNCTION fn_rechazar_cambio_movimiento_fiado() RETURNS trigger
                LANGUAGE plpgsql
                SECURITY INVOKER
                SET search_path = public
            AS $$
            BEGIN
                RAISE EXCEPTION 'Los movimientos de fiado no se cambian ni se borran; se registra uno nuevo.';
            END;
            $$;

            CREATE TRIGGER trg_movimientos_fiado_inmutables
                BEFORE UPDATE OR DELETE ON movimientos_fiado
                FOR EACH ROW
                EXECUTE FUNCTION fn_rechazar_cambio_movimiento_fiado();
            SQL);
    }

    /** Drop the ledger, its triggers and their functions. */
    public function down(): void
    {
        RowLevelSecurity::disable('movimientos_fiado');
        Schema::dropIfExists('movimientos_fiado');
        DB::statement('DROP FUNCTION IF EXISTS fn_aplicar_movimiento_fiado()');
        DB::statement('DROP FUNCTION IF EXISTS fn_rechazar_cambio_movimiento_fiado()');
    }

    /** Role the application connects with, quoted as an identifier. */
    private function applicationRole(): string
    {
        $name = config('database.connections.pgsql.username');

        return '"'.str_replace('"', '""', is_string($name) ? $name : '').'"';
    }
};
