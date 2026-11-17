<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Let an account have the cliente role: a buyer of the negocio, not part of the staff. */
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('administrador', 'cajero', 'bodeguero', 'cliente'))");
    }

    /** Go back to the staff roles; the accounts of clientes cannot exist without their role, so they are removed. */
    public function down(): void
    {
        DB::statement("DELETE FROM users WHERE rol = 'cliente'");
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('administrador', 'cajero', 'bodeguero'))");
    }
};
