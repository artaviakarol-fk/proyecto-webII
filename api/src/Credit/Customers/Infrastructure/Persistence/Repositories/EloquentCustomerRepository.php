<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Credit\Customers\Domain\ValueObjects\CreditLimit;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerName;
use Src\Credit\Customers\Domain\ValueObjects\PhoneNumber;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CustomerModel;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Infrastructure\Persistence\EloquentPages;

/**
 * Every query also filters by negocio_id: row-level security already isolates the negocios,
 * and the explicit filter keeps the intent visible in the code.
 */
final readonly class EloquentCustomerRepository implements CustomerRepository
{
    /** Generate a time-ordered UUID for a new customer. */
    public function nextId(): CustomerId
    {
        return new CustomerId((string) Str::uuid7());
    }

    /** Insert or update the customer; saldo_actual is left out, only the movements of fiado write it. */
    public function save(Customer $customer): void
    {
        CustomerModel::query()->updateOrCreate(
            ['id' => $customer->id->value, 'negocio_id' => $customer->negocioId->value],
            [
                'nombre' => $customer->name->value,
                'telefono' => $customer->phone?->value,
                'limite_fiado' => $customer->creditLimit->amount->toDecimal(),
                'activo' => $customer->active,
            ],
        );
    }

    /** Find the customer scoped to its negocio. */
    public function find(CustomerId $id, NegocioId $negocioId): ?Customer
    {
        $model = CustomerModel::query()
            ->where('negocio_id', $negocioId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find the customer scoped to its negocio and lock its row until the transaction ends. */
    public function findForUpdate(CustomerId $id, NegocioId $negocioId): ?Customer
    {
        $model = CustomerModel::query()
            ->where('negocio_id', $negocioId->value)
            ->whereKey($id->value)
            ->lockForUpdate()
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** One page of the customers of the negocio ordered by name, optionally matching a name or phone. */
    public function search(NegocioId $negocioId, ?string $term, ?bool $active, PageRequest $page): Page
    {
        $query = CustomerModel::query()
            ->where('negocio_id', $negocioId->value)
            ->when($active !== null, fn (Builder $query) => $query->where('activo', $active))
            ->when($term !== null, function (Builder $query) use ($term): void {
                $pattern = '%'.addcslashes((string) $term, '%_\\').'%';
                $query->where(fn (Builder $match) => $match
                    ->where('nombre', 'ilike', $pattern)
                    ->orWhere('telefono', 'ilike', $pattern));
            });

        return EloquentPages::of($query->orderBy('nombre')->orderBy('id'), $page, $this->toDomain(...));
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(CustomerModel $model): Customer
    {
        return Customer::reconstitute(
            new CustomerId($model->id),
            new NegocioId($model->negocio_id),
            new CustomerName($model->nombre),
            $model->telefono === null ? null : new PhoneNumber($model->telefono),
            CreditLimit::reconstitute(Money::fromDecimal($model->limite_fiado)),
            Money::fromDecimal($model->saldo_actual),
            $model->activo,
        );
    }
}
