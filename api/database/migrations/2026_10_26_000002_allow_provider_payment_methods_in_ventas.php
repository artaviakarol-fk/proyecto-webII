<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** A sale can also be paid by card or SINPE Móvil through a payment provider. */
    public function up(): void
    {
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT ventas_metodo_pago_check');
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_metodo_pago_check CHECK (metodo_pago IN ('efectivo', 'tarjeta', 'sinpe_movil'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ventas DROP CONSTRAINT ventas_metodo_pago_check');
        DB::statement("ALTER TABLE ventas ADD CONSTRAINT ventas_metodo_pago_check CHECK (metodo_pago IN ('efectivo'))");
    }
};
