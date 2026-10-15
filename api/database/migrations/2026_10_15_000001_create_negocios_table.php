<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the tenants table: every row of the domain belongs to one negocio. */
    public function up(): void
    {
        Schema::create('negocios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nombre', 120);
            $table->string('logo_path', 255)->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE negocios ADD CONSTRAINT negocios_estado_check CHECK (estado IN ('activo', 'inactivo'))");
    }

    /** Drop the tenants table. */
    public function down(): void
    {
        Schema::dropIfExists('negocios');
    }
};
