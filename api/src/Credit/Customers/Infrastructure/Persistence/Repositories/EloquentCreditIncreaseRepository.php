<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Str;
use Src\Credit\Customers\Domain\Contracts\CreditIncreaseRepository;
use Src\Credit\Customers\Domain\Entities\CreditIncrease;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\IncreaseReason;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CreditIncreaseModel;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class EloquentCreditIncreaseRepository implements CreditIncreaseRepository
{
    /** Generate a time-ordered UUID for a new increase. */
    public function nextId(): CreditIncreaseId
    {
        return new CreditIncreaseId((string) Str::uuid7());
    }

    /** Insert the increase, or record its reversion; the rest of the row never changes. */
    public function save(CreditIncrease $increase): void
    {
        CreditIncreaseModel::query()->updateOrCreate(
            ['id' => $increase->id->value, 'negocio_id' => $increase->negocioId->value],
            [
                'cliente_id' => $increase->customerId->value,
                'monto' => $increase->amount->toDecimal(),
                'motivo' => $increase->reason->value,
                'usuario_id' => $increase->grantedBy,
                'vigente_hasta' => $increase->validUntil,
                'revertida_at' => $increase->revertedAt,
                'revertida_por' => $increase->revertedBy,
                'created_at' => $increase->grantedAt,
            ],
        );
    }

    /** Find the increase scoped to its negocio and lock its row. */
    public function findForUpdate(CreditIncreaseId $id, NegocioId $negocioId): ?CreditIncrease
    {
        $model = CreditIncreaseModel::query()
            ->where('negocio_id', $negocioId->value)
            ->whereKey($id->value)
            ->lockForUpdate()
            ->first();

        return $model === null ? null : CreditIncrease::reconstitute(
            new CreditIncreaseId($model->id),
            new NegocioId($model->negocio_id),
            new CustomerId($model->cliente_id),
            Money::fromDecimal($model->monto),
            new IncreaseReason($model->motivo),
            $model->usuario_id,
            $model->created_at,
            $model->vigente_hasta,
            $model->revertida_at,
            $model->revertida_por,
        );
    }
}
