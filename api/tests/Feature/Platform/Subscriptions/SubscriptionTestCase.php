<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Subscriptions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Platform\Subscriptions\Application\Contracts\SubscriptionCharger;
use Tests\Support\Platform\FakeSubscriptionCharger;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

abstract class SubscriptionTestCase extends TestCase
{
    use RefreshDatabaseAsOwner;

    protected const string PROHIBIDO = 'http://localhost/problemas/prohibido';

    protected NegocioModel $negocio;

    protected FakeSubscriptionCharger $charger;

    /** One negocio with its rent paid and a provider that answers what the test says. */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-19 10:00:00');
        $this->negocio = NegocioModel::factory()->create();
        $this->charger = new FakeSubscriptionCharger;
        $this->app->instance(SubscriptionCharger::class, $this->charger);
    }

    /** Move the date the rent of the negocio is paid until. */
    protected function expiresIn(string $modifier, bool $trial = false): void
    {
        DB::table('suscripciones')->where('negocio_id', $this->negocio->id)->update([
            'vence_el' => Carbon::now()->modify($modifier),
            'en_prueba' => $trial,
        ]);
    }

    /** The date the rent of the negocio is paid until, in the time zone of the application. */
    protected function paidUntil(): string
    {
        return Carbon::parse((string) DB::table('suscripciones')->where('negocio_id', $this->negocio->id)->value('vence_el'))->toDateTimeString();
    }

    /** An account of the negocio with a role. */
    protected function account(Role $role): UserModel
    {
        return UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]);
    }

    /** Log in as an account of the negocio with the abilities of its role. */
    protected function actAs(Role $role): UserModel
    {
        $user = $this->account($role);
        Sanctum::actingAs($user, $role->abilities());

        return $user;
    }

    /** Log in as a superadmin of the platform. */
    protected function actAsSuperAdmin(): void
    {
        Sanctum::actingAs(UserModel::factory()->role(Role::SuperAdmin)->create(), Role::SuperAdmin->abilities());
    }
}
