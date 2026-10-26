<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\PaymentAlreadyStartedException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentMethod;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Billing\Payments\Domain\ValueObjects\SaleId;
use Src\Billing\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class EloquentPaymentRepository implements PaymentRepository
{
    /** Name of the index that lets a sale have only one pending or paid payment. */
    public const string LIVE_PAYMENT_INDEX = 'pagos_venta_viva_unique';

    /** Generate a time-ordered UUID for a new payment. */
    public function nextId(): PaymentId
    {
        return new PaymentId((string) Str::uuid7());
    }

    /** Find a payment of the negocio. */
    public function find(PaymentId $id, NegocioId $negocioId): ?Payment
    {
        $model = PaymentModel::query()->where('negocio_id', $negocioId->value)->whereKey($id->value)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find the pending or paid payment of a sale. */
    public function findLiveForSale(SaleId $saleId, NegocioId $negocioId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('negocio_id', $negocioId->value)
            ->where('venta_id', $saleId->value)
            ->whereIn('estado', [PaymentStatus::Pending->value, PaymentStatus::Paid->value])
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find the newest payment of a sale. */
    public function findLatestForSale(SaleId $saleId, NegocioId $negocioId): ?Payment
    {
        $model = PaymentModel::query()
            ->where('negocio_id', $negocioId->value)
            ->where('venta_id', $saleId->value)
            ->orderByDesc('created_at')
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Insert the payment or update the one with the same identifier; the index of live payments stops a second one for the sale. */
    public function save(Payment $payment): void
    {
        try {
            PaymentModel::query()->updateOrCreate(['id' => $payment->id->value], [
                'negocio_id' => $payment->negocioId->value,
                'venta_id' => $payment->saleId->value,
                'proveedor' => $payment->provider,
                'metodo_pago' => $payment->method->value,
                'moneda' => 'CRC',
                'monto' => $payment->amount->toDecimal(),
                'estado' => $payment->status->value,
                'referencia_externa' => $payment->reference?->value,
                'codigo_rechazo' => $payment->declineCode,
                'payload' => $payment->payload === [] ? null : $payment->payload,
                'resuelto_at' => $payment->settledAt,
                'created_at' => $payment->createdAt,
            ]);
        } catch (UniqueConstraintViolationException $violation) {
            throw str_contains($violation->getMessage(), self::LIVE_PAYMENT_INDEX) ? PaymentAlreadyStartedException::create() : $violation;
        }
    }

    /** Rebuild the payment from its row. */
    private function toDomain(PaymentModel $model): Payment
    {
        return Payment::reconstitute(
            new PaymentId($model->id),
            new NegocioId($model->negocio_id),
            new SaleId((string) $model->venta_id),
            $model->proveedor,
            PaymentMethod::from($model->metodo_pago),
            Money::fromDecimal($model->monto),
            PaymentStatus::from($model->estado),
            $model->referencia_externa === null ? null : new ChargeReference($model->referencia_externa),
            $model->codigo_rechazo,
            $model->payload ?? [],
            $model->created_at->toDateTimeImmutable(),
            $model->resuelto_at?->toDateTimeImmutable(),
        );
    }
}
