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
     * Keep every attempt to pay a sale: the provider, its reference, the amount in colones, the stage and the
     * answer of the provider. A partial unique index lets a sale have at most one pending or paid payment, so a
     * second attempt is refused instead of charging twice; a failed one leaves room for another. The sale and
     * the payment must belong to the same negocio, and the table is protected by row-level security.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT ventas_id_negocio_unique UNIQUE (id, negocio_id)');

        Schema::create('pagos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->uuid('venta_id')->nullable();
            // The credit payments (abonos) arrive with the fiado; their foreign key is added then.
            $table->uuid('abono_id')->nullable();
            $table->string('proveedor', 20);
            $table->string('metodo_pago', 20);
            $table->char('moneda', 3)->default('CRC');
            $table->decimal('monto', 12, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->string('referencia_externa', 255)->nullable();
            $table->string('codigo_rechazo', 60)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('resuelto_at')->nullable();
            $table->timestampsTz();

            $table->unique(['proveedor', 'referencia_externa']);
            $table->index(['negocio_id', 'venta_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE pagos
                ADD CONSTRAINT pagos_venta_mismo_negocio_foreign
                    FOREIGN KEY (venta_id, negocio_id) REFERENCES ventas (id, negocio_id) ON DELETE CASCADE,
                ADD CONSTRAINT pagos_proveedor_check CHECK (proveedor IN ('onvo', 'paypal')),
                ADD CONSTRAINT pagos_metodo_pago_check CHECK (metodo_pago IN ('tarjeta', 'sinpe_movil')),
                ADD CONSTRAINT pagos_estado_check CHECK (estado IN ('pendiente', 'pagado', 'fallido')),
                ADD CONSTRAINT pagos_monto_check CHECK (monto > 0),
                ADD CONSTRAINT pagos_destino_check CHECK (venta_id IS NOT NULL OR abono_id IS NOT NULL),
                ADD CONSTRAINT pagos_resuelto_at_check CHECK ((estado = 'pendiente') = (resuelto_at IS NULL)),
                ADD CONSTRAINT pagos_codigo_rechazo_check CHECK (estado = 'fallido' OR codigo_rechazo IS NULL)
            SQL);

        DB::statement("CREATE UNIQUE INDEX pagos_venta_viva_unique ON pagos (venta_id) WHERE estado IN ('pendiente', 'pagado')");

        RowLevelSecurity::enable('pagos');
    }

    /** Drop the payments and the key that tied them to the sales. */
    public function down(): void
    {
        RowLevelSecurity::disable('pagos');

        Schema::dropIfExists('pagos');

        DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_id_negocio_unique');
    }
};
