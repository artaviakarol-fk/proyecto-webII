<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Infrastructure\Persistence\Queries;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Sales\Reports\Application\Contracts\DailyClosingReader;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class DatabaseDailyClosingReader implements DailyClosingReader
{
    /**
     * Add the paid sales of the negocio whose payment fell on the day of the negocio (midnight to midnight, Costa Rica
     * time), grouped by the way of paying, in one query.
     *
     * @return array<string, array{count: int, total: Money}>
     */
    public function collectedOn(NegocioId $negocioId, DateTimeImmutable $day): array
    {
        $start = CarbonImmutable::instance($day)->startOfDay();

        $rows = DB::table('ventas')
            ->where('negocio_id', $negocioId->value)
            ->where('estado', 'cobrada')
            ->where('cobrada_at', '>=', $start)
            ->where('cobrada_at', '<', $start->addDay())
            ->groupBy('metodo_pago')
            ->selectRaw('metodo_pago, count(*) as cantidad, sum(total) as total')
            ->get();

        $collected = [];

        foreach ($rows as $row) {
            $collected[(string) $row->metodo_pago] = ['count' => (int) $row->cantidad, 'total' => Money::fromDecimal((string) $row->total)];
        }

        return $collected;
    }
}
