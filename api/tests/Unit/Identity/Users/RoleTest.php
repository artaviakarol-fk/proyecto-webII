<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users;

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
}
