<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create the staff accounts table; every account belongs to exactly one negocio. */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('correo', 255)->unique();
            $table->string('password', 255);
            $table->string('rol', 20);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('administrador', 'cajero', 'bodeguero'))");
    }

    /** Drop the staff accounts table. */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
