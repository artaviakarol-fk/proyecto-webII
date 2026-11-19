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
     * Let each negocio create roles of its own. The default roles (administrador, cajero, bodeguero, supervisor) stay in
     * the code and cannot be changed; a role of a negocio is a name plus some of the permissions that already exist,
     * so a role can never ask for something the API does not know how to check. The roles of a negocio are only seen
     * by that negocio, through row-level security.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->jsonb('permisos');
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX roles_negocio_nombre_unique ON roles (negocio_id, lower(nombre))');
        DB::statement(<<<'SQL'
            ALTER TABLE roles ADD CONSTRAINT roles_permisos_check CHECK (
                jsonb_typeof(permisos) = 'array'
                AND jsonb_array_length(permisos) > 0
                AND permisos <@ '["administrar", "caja", "inventario"]'::jsonb
            )
            SQL);
        RowLevelSecurity::enable('roles');

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('rol_id')->nullable()->constrained('roles')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('superadmin', 'administrador', 'cajero', 'bodeguero', 'supervisor', 'personalizado', 'cliente'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_personalizado_check CHECK ((rol = 'personalizado') = (rol_id IS NOT NULL))");
    }

    /** Go back to the default roles only; the accounts with a role of their negocio cannot keep it, so they are removed. */
    public function down(): void
    {
        DB::statement("DELETE FROM users WHERE rol IN ('personalizado', 'supervisor')");
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_personalizado_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('superadmin', 'administrador', 'cajero', 'bodeguero', 'cliente'))");

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rol_id');
        });

        RowLevelSecurity::disable('roles');
        Schema::dropIfExists('roles');
    }
};
