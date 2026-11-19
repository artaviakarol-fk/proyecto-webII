<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionPaymentRepository;
use Src\Platform\Subscriptions\Domain\Entities\SubscriptionPayment;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentMethod;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionPaymentStatus;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;
use stdClass;

/** The payments of the rent, in the pagos_suscripcion table of the platform. */
final readonly class DatabaseSubscriptionPaymentRepository implements SubscriptionPaymentRepository
{
    /** Answer a new time-ordered identifier. */
    public function nextId(): string
    {
        return (string) Str::uuid7();
    }

    /** A payment of the negocio, locked until the transaction ends. */
    public function findForUpdate(string $id, NegocioId $negocioId): ?SubscriptionPayment
    {
        $row = DB::table('pagos_suscripcion')->where('negocio_id', $negocioId->value)->where('id', $id)->lockForUpdate()->first();

        return $row === null ? null : $this->toDomain($row);
    }

    /** The payment a provider knows by its reference, locked until the transaction ends. */
    public function findByReferenceForUpdate(string $provider, string $reference): ?SubscriptionPayment
    {
        $row = DB::table('pagos_suscripcion')->where('proveedor', $provider)->where('referencia', $reference)->lockForUpdate()->first();

        return $row === null ? null : $this->toDomain($row);
    }

    /**
     * The last payments of a negocio, the newest first.
     *
     * @return list<SubscriptionPayment>
     */
    public function latestOf(NegocioId $negocioId, int $limit): array
    {
        return array_values(array_map(
            fn (stdClass $row): SubscriptionPayment => $this->toDomain($row),
            DB::table('pagos_suscripcion')->where('negocio_id', $negocioId->value)->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()->all(),
        ));
    }

    /** Insert a new payment or write its changes. */
    public function save(SubscriptionPayment $payment): void
    {
        DB::table('pagos_suscripcion')->upsert(
            [[
                'id' => $payment->id,
                'negocio_id' => $payment->negocioId->value,
                'monto' => $payment->amount->toDecimal(),
                'meses' => $payment->months,
                'metodo' => $payment->method->value,
                'proveedor' => $payment->provider,
                'referencia' => $payment->reference,
                'estado' => $payment->status->value,
                'registrado_por' => $payment->registeredBy,
                'respuesta_proveedor' => $payment->providerAnswer === [] ? null : json_encode($payment->providerAnswer, JSON_THROW_ON_ERROR),
                'aplicado_el' => $payment->appliedAt,
                'created_at' => $payment->createdAt,
                'updated_at' => now(),
            ]],
            ['id'],
            ['referencia', 'estado', 'respuesta_proveedor', 'aplicado_el', 'updated_at'],
        );
    }

    /** Rebuild a payment from its row. */
    private function toDomain(stdClass $row): SubscriptionPayment
    {
        $answer = $row->respuesta_proveedor === null ? [] : json_decode((string) $row->respuesta_proveedor, true, flags: JSON_THROW_ON_ERROR);

        return SubscriptionPayment::reconstitute(
            (string) $row->id,
            new NegocioId((string) $row->negocio_id),
            Money::fromDecimal((string) $row->monto),
            (int) $row->meses,
            SubscriptionPaymentMethod::from((string) $row->metodo),
            (string) $row->proveedor,
            $row->referencia === null ? null : (string) $row->referencia,
            SubscriptionPaymentStatus::from((string) $row->estado),
            $row->registrado_por === null ? null : (string) $row->registrado_por,
            is_array($answer) ? $this->stringKeys($answer) : [],
            $row->aplicado_el === null ? null : CarbonImmutable::parse((string) $row->aplicado_el)->toDateTimeImmutable(),
            CarbonImmutable::parse((string) $row->created_at)->toDateTimeImmutable(),
        );
    }

    /**
     * The answer of the provider with its keys as text.
     *
     * @param  array<mixed>  $answer
     * @return array<string, mixed>
     */
    private function stringKeys(array $answer): array
    {
        $kept = [];
        foreach ($answer as $key => $value) {
            $kept[(string) $key] = $value;
        }

        return $kept;
    }
}
