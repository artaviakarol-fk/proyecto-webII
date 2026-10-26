<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a paid payment be refunded: the stage "reembolsado" is allowed and the id of the refund at the provider is
     * kept to reconcile it. A refunded payment always has that id and no other stage has it.
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->string('referencia_reembolso', 255)->nullable()->after('referencia_externa');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE pagos
                DROP CONSTRAINT pagos_estado_check,
                ADD CONSTRAINT pagos_estado_check CHECK (estado IN ('pendiente', 'pagado', 'fallido', 'reembolsado')),
                ADD CONSTRAINT pagos_referencia_reembolso_check CHECK ((estado = 'reembolsado') = (referencia_reembolso IS NOT NULL))
            SQL);
    }

    /** Go back to payments without refunds; it fails while a refunded payment exists, so none is lost. */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE pagos
                DROP CONSTRAINT pagos_referencia_reembolso_check,
                DROP CONSTRAINT pagos_estado_check,
                ADD CONSTRAINT pagos_estado_check CHECK (estado IN ('pendiente', 'pagado', 'fallido'))
            SQL);

        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropColumn('referencia_reembolso');
        });
    }
};
