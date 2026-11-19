<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Domain;

use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Domain\ValueObjects\Role;

final class RoleTest extends TestCase
{
    /** The administrator can do everything the other roles do and administrate. */
    public function test_administrator_has_every_ability(): void
    {
        $this->assertSame(['administrar', 'caja', 'inventario'], Role::Administrador->abilities());
    }

    /** The cashier and the warehouse keeper only reach their own area. */
    public function test_cashier_and_warehouse_keeper_have_one_ability_each(): void
    {
        $this->assertSame(['caja'], Role::Cajero->abilities());
        $this->assertSame(['inventario'], Role::Bodeguero->abilities());
    }

    /** The cliente only buys: its token carries no ability of the staff, and it is not part of the staff. */
    public function test_cliente_only_buys_and_is_not_staff(): void
    {
        $this->assertSame(['comprar'], Role::Cliente->abilities());
        $this->assertNotContains(Role::Cliente, Role::staff());
        $this->assertSame([Role::Administrador, Role::Cajero, Role::Bodeguero, Role::Supervisor, Role::Personalizado], Role::staff());
    }

    /** The supervisor attends the till and receives the merchandise, but does not administrate. */
    public function test_supervisor_has_till_and_warehouse(): void
    {
        $this->assertSame(['caja', 'inventario'], Role::Supervisor->abilities());
    }

    /** Every negocio starts with the same four roles, which nobody can change. */
    public function test_default_roles_of_every_negocio(): void
    {
        $this->assertSame([Role::Administrador, Role::Cajero, Role::Bodeguero, Role::Supervisor], Role::defaults());
    }

    /** The superadmin only reaches the platform. */
    public function test_superadmin_only_reaches_the_platform(): void
    {
        $this->assertSame(['plataforma'], Role::SuperAdmin->abilities());
        $this->assertNotContains(Role::SuperAdmin, Role::staff());
    }
}
