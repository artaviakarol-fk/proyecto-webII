<?php

declare(strict_types=1);

namespace Src\Sales\Reports\Infrastructure\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

/** The day of the closing: ?fecha=2026-11-10. Without it, today. */
final class DailyClosingRequest extends FormRequest
{
    /**
     * Validation rules for the day.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Día del cierre, en hora de Costa Rica (AAAA-MM-DD). Si no se envía, es hoy. */
            'fecha' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    /** The day asked for, or today in the time of the negocio. */
    public function day(): DateTimeImmutable
    {
        $zone = new DateTimeZone((string) config('app.timezone'));

        return $this->filled('fecha')
            ? new DateTimeImmutable($this->string('fecha')->toString(), $zone)
            : new DateTimeImmutable('today', $zone);
    }
}
