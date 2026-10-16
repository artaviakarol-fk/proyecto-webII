<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keep the id number of the buyer on the sales that need it, such as the ones with liquor. */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->string('cedula_comprador', 20)->nullable()->after('metodo_pago');
        });
    }

    /** Drop the id number of the buyer. */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table): void {
            $table->dropColumn('cedula_comprador');
        });
    }
};
