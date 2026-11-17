<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Inventory\Products\Application\Contracts\ReservedUnitsCounter;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseReservedUnitsCounter implements ReservedUnitsCounter
{
    public const string ACTIVE = 'activa';

    /** Add up the active reservas of the product whose time is not over. */
    public function of(ProductId $productId, NegocioId $negocioId, DateTimeImmutable $now): int
    {
        return (int) DB::table('reservas')
            ->where('negocio_id', $negocioId->value)
            ->where('producto_id', $productId->value)
            ->where('estado', self::ACTIVE)
            ->where('expira_en', '>', $now)
            ->sum('cantidad');
    }
}
