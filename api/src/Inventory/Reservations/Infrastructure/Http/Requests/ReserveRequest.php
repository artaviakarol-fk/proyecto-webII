<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Inventory\Reservations\Application\DTOs\ReserveCommand;
use Src\Inventory\Reservations\Domain\Entities\Reservation;

/** Units of a product the cliente wants to hold: "producto_id" and "cantidad". Who reserves comes from the token. */
final class ReserveRequest extends FormRequest
{
    /**
     * Validation rules for the product and the units.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'uuid'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.Reservation::MAX_QUANTITY],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(): ReserveCommand
    {
        return new ReserveCommand(
            $this->string('producto_id')->lower()->toString(),
            (int) $this->input('cantidad'),
            now()->toDateTimeImmutable(),
        );
    }
}
