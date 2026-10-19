<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Infrastructure\Tenancy\AuthenticatedNegocioContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class AuthenticatedNegocioContextTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The port is bound to the adapter that reads the authenticated account. */
    public function test_port_is_bound_to_the_authenticated_account_adapter(): void
    {
        $this->assertInstanceOf(AuthenticatedNegocioContext::class, $this->app->make(NegocioContext::class));
    }

    /** Every role works on the negocio its account belongs to. */
    #[DataProvider('roles')]
    public function test_account_works_on_its_own_negocio(Role $role): void
    {
        $negocio = NegocioModel::factory()->create();
        Sanctum::actingAs(UserModel::factory()->role($role)->create(['negocio_id' => $negocio->id]));

        $this->assertSame($negocio->id, $this->app->make(NegocioContext::class)->current()->value);
    }

    /**
     * Roles of the staff.
     *
     * @return array<string, array{Role}>
     */
    public static function roles(): array
    {
        return array_combine(
            array_map(fn (Role $role): string => $role->value, Role::cases()),
            array_map(fn (Role $role): array => [$role], Role::cases()),
        );
    }

    /** Without an authenticated account there is no negocio to work on. */
    public function test_guest_is_not_authenticated(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->app->make(NegocioContext::class)->current();
    }
}
