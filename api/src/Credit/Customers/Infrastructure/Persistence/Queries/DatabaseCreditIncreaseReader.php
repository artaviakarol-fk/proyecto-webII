<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Queries;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Credit\Customers\Application\Contracts\CreditIncreaseReader;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseFilter;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseSummary;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use stdClass;

/** Reads the increases with the names of the customer, the cashier who gave them and who reverted them. */
final readonly class DatabaseCreditIncreaseReader implements CreditIncreaseReader
{
    /** One increase of the negocio. */
    public function find(CreditIncreaseId $id, NegocioId $negocioId, DateTimeImmutable $at): ?CreditIncreaseSummary
    {
        $row = $this->query($negocioId)->where('a.id', $id->value)->first();

        return $row === null ? null : $this->summary($row, $at);
    }

    /** One page of the increases of the negocio, the newest first. */
    public function list(NegocioId $negocioId, CreditIncreaseFilter $filter, PageRequest $page): Page
    {
        $query = $this->query($negocioId)
            ->when($filter->customerId !== null, fn (Builder $query) => $query->where('a.cliente_id', $filter->customerId))
            ->when($filter->onlyActive, fn (Builder $query) => $query->whereNull('a.revertida_at')->where('a.vigente_hasta', '>', $filter->at));

        $total = $query->getCountForPagination();

        $rows = $total > $page->offset()
            ? $query->orderByDesc('a.created_at')->orderByDesc('a.id')->forPage($page->number, $page->size)->get()->all()
            : [];

        $increases = [];

        foreach ($rows as $row) {
            $increases[] = $this->summary($row, $filter->at);
        }

        return new Page($increases, $total, $page);
    }

    /** The increases of the negocio with the names they need. */
    private function query(NegocioId $negocioId): Builder
    {
        return DB::table('ampliaciones_limite as a')
            ->join('clientes as c', 'c.id', '=', 'a.cliente_id')
            ->join('users as u', 'u.id', '=', 'a.usuario_id')
            ->leftJoin('users as r', 'r.id', '=', 'a.revertida_por')
            ->where('a.negocio_id', $negocioId->value)
            ->select([
                'a.id', 'a.cliente_id', 'c.nombre as cliente_nombre', 'a.monto', 'a.motivo',
                'a.usuario_id', 'u.nombre as usuario_nombre', 'a.created_at', 'a.vigente_hasta',
                'a.revertida_at', 'a.revertida_por', 'r.nombre as revertida_por_nombre',
            ]);
    }

    /** Turn a row into the summary; it is active if it was not reverted and has not expired at the moment. */
    private function summary(stdClass $row, DateTimeImmutable $at): CreditIncreaseSummary
    {
        $validUntil = CarbonImmutable::parse((string) $row->vigente_hasta);

        return new CreditIncreaseSummary(
            (string) $row->id,
            (string) $row->cliente_id,
            (string) $row->cliente_nombre,
            (string) $row->monto,
            (string) $row->motivo,
            (string) $row->usuario_id,
            (string) $row->usuario_nombre,
            $this->moment((string) $row->created_at),
            $this->moment((string) $row->vigente_hasta),
            $row->revertida_at === null ? null : $this->moment((string) $row->revertida_at),
            $row->revertida_por === null ? null : (string) $row->revertida_por,
            $row->revertida_por_nombre === null ? null : (string) $row->revertida_por_nombre,
            $row->revertida_at === null && $at < $validUntil,
        );
    }

    /** Write a stored moment in the timezone of the application, as ISO 8601. */
    private function moment(string $value): string
    {
        return CarbonImmutable::parse($value)->setTimezone((string) config('app.timezone'))->toIso8601String();
    }
}
