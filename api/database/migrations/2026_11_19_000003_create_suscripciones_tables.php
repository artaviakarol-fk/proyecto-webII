<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rent of the platform: each negocio pays a month at a time and has a date until which its service is paid.
     *
     * These tables belong to the platform, not to the negocio, so they have no row-level security: the superadmins
     * read every negocio, and an administrator reaches only its own because every query filters by the negocio of
     * its token. They never hold sales, customers or anything else of the negocio.
     */
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table): void {
            $table->foreignUuid('negocio_id')->primary()->constrained('negocios')->cascadeOnDelete();
            $table->timestampTz('vence_el');
            $table->boolean('en_prueba')->default(false);
            $table->timestampsTz();
        });

        Schema::create('pagos_suscripcion', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->integer('meses');
            $table->string('metodo', 20);
            $table->string('proveedor', 20);
            $table->string('referencia', 100)->nullable();
            $table->string('estado', 20);
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('respuesta_proveedor')->nullable();
            $table->timestampTz('aplicado_el')->nullable();
            $table->timestampsTz();

            $table->unique(['proveedor', 'referencia']);
            $table->index(['negocio_id', 'created_at']);
        });

        DB::statement('ALTER TABLE pagos_suscripcion ADD CONSTRAINT pagos_suscripcion_monto_check CHECK (monto > 0)');
        DB::statement('ALTER TABLE pagos_suscripcion ADD CONSTRAINT pagos_suscripcion_meses_check CHECK (meses BETWEEN 1 AND 12)');
        DB::statement("ALTER TABLE pagos_suscripcion ADD CONSTRAINT pagos_suscripcion_metodo_check CHECK (metodo IN ('efectivo', 'sinpe', 'tarjeta', 'sinpe_movil'))");
        DB::statement("ALTER TABLE pagos_suscripcion ADD CONSTRAINT pagos_suscripcion_proveedor_check CHECK (proveedor IN ('manual', 'onvo', 'paypal'))");
        DB::statement("ALTER TABLE pagos_suscripcion ADD CONSTRAINT pagos_suscripcion_estado_check CHECK (estado IN ('pendiente', 'aprobado', 'rechazado'))");

        // The negocios that already exist keep working: they start with one paid month from today.
        DB::statement("INSERT INTO suscripciones (negocio_id, vence_el, en_prueba, created_at, updated_at) SELECT id, now() + interval '1 month', false, now(), now() FROM negocios");
    }

    /** Drop the rent of the platform. */
    public function down(): void
    {
        Schema::dropIfExists('pagos_suscripcion');
        Schema::dropIfExists('suscripciones');
    }
};
