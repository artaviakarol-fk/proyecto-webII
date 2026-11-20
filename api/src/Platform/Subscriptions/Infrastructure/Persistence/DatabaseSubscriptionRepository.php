<?php

declare(strict_types=1);

namespace Src\Platform\Subscriptions\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Platform\Subscriptions\Domain\Contracts\SubscriptionRepository;
use Src\Platform\Subscriptions\Domain\Entities\Subscription;
use Src\Shared\Domain\ValueObjects\NegocioId;
use stdClass;

/** The rent of each negocio, in the suscripciones table of the platform. */
final readonly class DatabaseSubscriptionRepository implements SubscriptionRepository
{
    /** The rent of a negocio. */
    public function find(NegocioId $negocioId): ?Subscription
    {
        $row = DB::table('suscripciones')->where('negocio_id', $negocioId->value)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    /** The rent of a negocio, locked until the transaction ends. */
    public function findForUpdate(NegocioId $negocioId): ?Subscription
    {
        $row = DB::table('suscripciones')->where('negocio_id', $negocioId->value)->lockForUpdate()->first();

        return $row === null ? null : $this->toDomain($row);
    }

    /** Whether the platform keeps the negocio turned on. */
    public function negocioIsActive(NegocioId $negocioId): bool
    {
        return DB::table('negocios')->where('id', $negocioId->value)->value('estado') === NegocioModel::ACTIVE;
    }

    /**
     * The rent and whether the negocio is turned on, with one indexed read.
     *
     * @return array{Subscription, bool}|null
     */
    public function serviceState(NegocioId $negocioId): ?array
    {
        $row = DB::table('suscripciones as s')
            ->join('negocios as n', 'n.id', '=', 's.negocio_id')
            ->where('s.negocio_id', $negocioId->value)
            ->first(['s.negocio_id', 's.vence_el', 's.en_prueba', 'n.estado']);

        return $row === null ? null : [$this->toDomain($row), $row->estado === NegocioModel::ACTIVE];
    }

    /** Insert the rent of a negocio or write its new date. */
    public function save(Subscription $subscription): void
    {
        DB::table('suscripciones')->upsert(
            [[
                'negocio_id' => $subscription->negocioId->value,
                'vence_el' => $subscription->expiresAt,
                'en_prueba' => $subscription->trial,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['negocio_id'],
            ['vence_el', 'en_prueba', 'updated_at'],
        );
    }

    /** Rebuild the rent from its row. */
    private function toDomain(stdClass $row): Subscription
    {
        return Subscription::reconstitute(
            new NegocioId((string) $row->negocio_id),
            CarbonImmutable::parse((string) $row->vence_el)->toDateTimeImmutable(),
            (bool) $row->en_prueba,
        );
    }
}
