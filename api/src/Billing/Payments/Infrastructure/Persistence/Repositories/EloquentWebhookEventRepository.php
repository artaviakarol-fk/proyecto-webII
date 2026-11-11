<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Src\Billing\Payments\Domain\Contracts\WebhookEventRepository;
use Src\Billing\Payments\Domain\ValueObjects\WebhookEvent;
use Src\Billing\Payments\Infrastructure\Persistence\Models\WebhookEventModel;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class EloquentWebhookEventRepository implements WebhookEventRepository
{
    /**
     * Insert the event, doing nothing when the provider and the event id are already there. The conflict is answered by
     * the database itself (ON CONFLICT DO NOTHING) because a failed insert would abort the open transaction.
     * Only the ids are kept, never the body of the notification.
     */
    public function register(NegocioId $negocioId, string $provider, WebhookEvent $event, DateTimeImmutable $now): bool
    {
        $inserted = WebhookEventModel::query()->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $negocioId->value,
            'proveedor' => $provider,
            'evento_id' => $event->id,
            'tipo' => $event->type,
            'payload' => json_encode(['referencia' => $event->reference->value], JSON_THROW_ON_ERROR),
            'procesado_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === 1;
    }
}
