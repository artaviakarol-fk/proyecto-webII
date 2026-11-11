<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Credit\Customers\Domain\ValueObjects\IncreaseReason;

/** An exceptional increase of the limit: how much and why. */
final class GrantCreditIncreaseRequest extends FormRequest
{
    /**
     * Validation rules; the maximum of the limit is checked by the use case.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Monto en colones que se suma al límite hasta el final del día. */
            'monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            /** Por qué se amplía el límite. */
            'motivo' => ['required', 'string', 'max:'.IncreaseReason::MAX_LENGTH],
        ];
    }

    /** Amount as a decimal string. */
    public function amount(): string
    {
        return (string) $this->input('monto');
    }

    /** Reason of the increase. */
    public function reason(): string
    {
        return $this->string('motivo')->toString();
    }
}
