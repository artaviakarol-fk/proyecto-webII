<?php

declare(strict_types=1);

namespace Src\Inventory\Restock\Infrastructure\Http\Requests;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** The days of sales the report looks at: ?dias=30. Without them, 30. */
final class RestockRequest extends FormRequest
{
    /** Days looked at when none are asked for. */
    public const int DEFAULT_DAYS = 30;

    /**
     * Validation rules for the days.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Días de ventas que se analizan, de 7 a 90. Si no se envía, 30. */
            'dias' => ['sometimes', 'integer', 'min:7', 'max:90'],
        ];
    }

    /** The days asked for, or the default. */
    public function days(): int
    {
        return $this->integer('dias', self::DEFAULT_DAYS);
    }

    /** The moment the report is read, from which the days are counted back. */
    public function asOf(): DateTimeImmutable
    {
        return CarbonImmutable::now()->toDateTimeImmutable();
    }
}
