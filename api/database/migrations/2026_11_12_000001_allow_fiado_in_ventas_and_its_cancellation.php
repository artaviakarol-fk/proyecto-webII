<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Let a sale be closed on fiado, and let a cancelled sale on fiado give its amount back to the customer
     * with a third kind of movement, the anulacion, which takes from the balance like an abono.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT ventas_metodo_pago_check');
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_metodo_pago_check CHECK (metodo_pago IN ('efectivo', 'tarjeta', 'sinpe_movil', 'fiado'))");

        DB::statement('ALTER TABLE movimientos_fiado DROP CONSTRAINT movimientos_fiado_tipo_check');
        DB::statement(<<<'SQL'
            ALTER TABLE movimientos_fiado ADD CONSTRAINT movimientos_fiado_tipo_check CHECK (
                (tipo IN ('cargo', 'anulacion') AND venta_id IS NOT NULL AND abono_id IS NULL)
                OR (tipo = 'abono' AND abono_id IS NOT NULL AND venta_id IS NULL)
            )
            SQL);

        // Una venta se anula una sola vez, así que su cargo se devuelve una sola vez.
        DB::statement("CREATE UNIQUE INDEX movimientos_fiado_anulacion_unique ON movimientos_fiado (venta_id) WHERE tipo = 'anulacion'");

        $this->applyMovementsWith("CASE NEW.tipo WHEN 'cargo' THEN NEW.monto ELSE -NEW.monto END");
    }

    /** Go back to cash and providers only, and to charges and abonos only. */
    public function down(): void
    {
        $this->applyMovementsWith("CASE NEW.tipo WHEN 'cargo' THEN NEW.monto ELSE -NEW.monto END");

        DB::statement('DROP INDEX IF EXISTS movimientos_fiado_anulacion_unique');
        DB::statement('ALTER TABLE movimientos_fiado DROP CONSTRAINT movimientos_fiado_tipo_check');
        DB::statement(<<<'SQL'
            ALTER TABLE movimientos_fiado ADD CONSTRAINT movimientos_fiado_tipo_check CHECK (
                (tipo = 'cargo' AND venta_id IS NOT NULL AND abono_id IS NULL)
                OR (tipo = 'abono' AND abono_id IS NOT NULL AND venta_id IS NULL)
            )
            SQL);

        DB::statement('ALTER TABLE ventas DROP CONSTRAINT ventas_metodo_pago_check');
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_metodo_pago_check CHECK (metodo_pago IN ('efectivo', 'tarjeta', 'sinpe_movil'))");
    }

    /** Replace the body of the trigger function that moves the balance: a charge adds, anything else takes. */
    private function applyMovementsWith(string $delta): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION fn_aplicar_movimiento_fiado() RETURNS trigger
                LANGUAGE plpgsql
                SECURITY DEFINER
                SET search_path = public
            AS \$\$
            BEGIN
                UPDATE clientes
                   SET saldo_actual = saldo_actual + {$delta},
                       updated_at = now()
                 WHERE id = NEW.cliente_id
                   AND negocio_id = NEW.negocio_id;

                IF NOT FOUND THEN
                    RAISE EXCEPTION 'El cliente % no pertenece al negocio del movimiento.', NEW.cliente_id
                        USING ERRCODE = 'foreign_key_violation';
                END IF;

                RETURN NEW;
            END;
            \$\$;
            SQL);
    }
};
