<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NegociosDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class DemoDataTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The seeder creates two fictitious negocios, each with its own administrator. */
    public function test_seeder_creates_two_negocios_with_their_administrators(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            [NegociosDemoSeeder::TERESITA_ID, NegociosDemoSeeder::LA_ESQUINA_ID],
            DB::table('negocios')->orderBy('id')->pluck('id')->all(),
        );

        foreach ([NegociosDemoSeeder::TERESITA_ID, NegociosDemoSeeder::LA_ESQUINA_ID] as $negocioId) {
            $this->assertDatabaseHas('users', ['negocio_id' => $negocioId, 'rol' => 'administrador', 'activo' => true]);
        }
    }

    /** Running the seeder twice leaves the same data. */
    public function test_seeder_can_run_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('negocios', 2);
        $this->assertDatabaseCount('users', 5);
    }

    /** Every demo account logs in with the documented password. */
    public function test_demo_accounts_log_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/v1/tokens', [
            'correo' => 'admin@laesquina.test',
            'contrasena' => NegociosDemoSeeder::PASSWORD,
            'dispositivo' => 'Demo',
        ])->assertCreated();
    }
}
