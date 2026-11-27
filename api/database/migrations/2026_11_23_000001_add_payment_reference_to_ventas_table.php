<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sale paid at the register by card (datáfono) or SINPE Móvil keeps the authorization or voucher number.
     * A sale paid through a payment provider has none: its proof is in the pagos table.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->string('referencia_pago', 60)->nullable();
        });

        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_referencia_pago_check CHECK (referencia_pago IS NULL OR metodo_pago IN ('tarjeta', 'sinpe_movil'))");
    }

    /** Drop the reference. */
    public function down(): void
    {
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT IF EXISTS ventas_referencia_pago_check');

        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn('referencia_pago');
        });
    }
};
