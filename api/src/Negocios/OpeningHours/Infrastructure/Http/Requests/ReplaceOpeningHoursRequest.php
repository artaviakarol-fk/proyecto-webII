<?php

declare(strict_types=1);

namespace Src\Negocios\OpeningHours\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Negocios\OpeningHours\Domain\ValueObjects\Weekday;
use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;

final class ReplaceOpeningHoursRequest extends FormRequest
{
    /**
     * Validation rules for the weekly schedule: the days by name, each with up to three slots.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $days = implode(',', array_map(static fn (Weekday $day): string => $day->key(), Weekday::cases()));

        return [
            'dias' => ['present', 'array:'.$days],
            'dias.*' => ['array', 'max:'.WeeklySchedule::MAX_SLOTS_PER_DAY],
            'dias.*.*' => ['array:abre,cierra'],
            'dias.*.*.abre' => ['required', 'date_format:H:i'],
            'dias.*.*.cierra' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * The opening and closing time of each slot, by day name.
     *
     * @return array<string, list<array{string, string}>>
     */
    public function days(): array
    {
        /** @var array<string, list<array{abre: string, cierra: string}>> $days */
        $days = $this->validated('dias');

        return array_map(
            static fn (array $slots): array => array_map(
                static fn (array $slot): array => [$slot['abre'], $slot['cierra']],
                $slots,
            ),
            $days,
        );
    }
}
