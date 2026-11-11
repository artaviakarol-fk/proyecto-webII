<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Sales\SaleDrafts\Domain\ValueObjects\SaleText;

/** What the cashier wrote. */
final class DraftSaleRequest extends FormRequest
{
    /**
     * Validation rules for the text.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Lo que escribió el cajero, por ejemplo "anote 2 leches y 1 pan a doña María en fiado". */
            'texto' => ['required', 'string', 'max:'.SaleText::MAX_LENGTH],
        ];
    }

    /** The text. */
    public function text(): string
    {
        return $this->string('texto')->toString();
    }
}
