<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let the platform have its own accounts. A superadmin runs the platform, not a negocio, so it is the only account
     * without a negocio; every other account still belongs to exactly one. An account opened by somebody else (the
     * first administrator of a negocio) must change its temporary password before it can do anything else.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('negocio_id')->nullable()->change();
            $table->boolean('debe_cambiar_contrasena')->default(false);
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('superadmin', 'administrador', 'cajero', 'bodeguero', 'cliente'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_negocio_segun_rol_check CHECK ((rol = 'superadmin') = (negocio_id IS NULL))");
    }

    /** Remove the accounts of the platform and make every account belong to a negocio again. */
    public function down(): void
    {
        DB::statement("DELETE FROM users WHERE rol = 'superadmin'");
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_negocio_segun_rol_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('administrador', 'cajero', 'bodeguero', 'cliente'))");

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('debe_cambiar_contrasena');
            $table->uuid('negocio_id')->nullable(false)->change();
        });
    }
};
