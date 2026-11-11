<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\DTOs;

use Src\Shared\Domain\ValueObjects\Money;

/**
 * A draft of a sale built from what the cashier wrote. It is never stored: the cashier reviews it and,
 * if it is right, opens the sale and closes it with the usual endpoints.
 */
final readonly class SaleDraft
{
    /**
     * Carry the draft.
     *
     * @param  list<DraftLine>  $lines
     * @param  list<InterpretedItem>  $notFound  products that match nothing in the catalog
     * @param  list<string>  $warnings  what the cashier has to check before confirming
     */
    public function __construct(
        public string $text,
        public string $paymentMethod,
        public ?string $customerWritten,
        public ?DraftCustomer $customer,
        public array $lines,
        public array $notFound,
        public Money $total,
        public ?bool $fitsFiado,
        public array $warnings,
    ) {}

    /** Ready to confirm when every product was found and has stock and, on fiado, the customer was found and the sale fits. */
    public function readyToConfirm(): bool
    {
        return $this->warnings === [];
    }
}
