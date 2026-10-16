<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Inventory\Expirations\Application\DTOs\MarkExpiredCommand;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredQuantity;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiryReason;

/** Units of a product found expired: "cantidad" and the "motivo". The product comes from the URL. */
final class MarkExpiredRequest extends FormRequest
{
    /**
     * Validation rules for the units and the reason.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.ExpiredQuantity::MAX],
            'motivo' => ['required', 'string', 'max:'.ExpiryReason::MAX_LENGTH],
        ];
    }

    /** Map the validated payload and the product of the URL to the use case input; who marks comes from the token. */
    public function toCommand(string $productId): MarkExpiredCommand
    {
        return new MarkExpiredCommand(
            $productId,
            (int) $this->input('cantidad'),
            $this->string('motivo')->toString(),
            now()->toDateTimeImmutable(),
        );
    }
}
