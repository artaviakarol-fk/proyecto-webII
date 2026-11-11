<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Entities;

use Src\Credit\Customers\Domain\ValueObjects\CreditLimit;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerName;
use Src\Credit\Customers\Domain\ValueObjects\PhoneNumber;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * A trusted customer who may buy on fiado up to a limit.
 *
 * The balance can be read but has no setter: it only moves through the movements of fiado
 * (sales on credit and payments), which the database applies to the customer.
 */
final class Customer
{
    /** Hold the full state of the customer. */
    private function __construct(
        public readonly CustomerId $id,
        public readonly NegocioId $negocioId,
        public private(set) CustomerName $name,
        public private(set) ?PhoneNumber $phone,
        public private(set) CreditLimit $creditLimit,
        public readonly Money $balance,
        public private(set) bool $active,
    ) {}

    /** Register a new customer of a negocio, active and owing nothing. */
    public static function register(
        CustomerId $id,
        NegocioId $negocioId,
        CustomerName $name,
        ?PhoneNumber $phone,
        CreditLimit $creditLimit,
    ): self {
        return new self($id, $negocioId, $name, $phone, $creditLimit, new Money(0), true);
    }

    /** Rebuild a customer from its stored state. */
    public static function reconstitute(
        CustomerId $id,
        NegocioId $negocioId,
        CustomerName $name,
        ?PhoneNumber $phone,
        CreditLimit $creditLimit,
        Money $balance,
        bool $active,
    ): self {
        return new self($id, $negocioId, $name, $phone, $creditLimit, $balance, $active);
    }

    /** Replace the contact data of the customer. */
    public function revise(CustomerName $name, ?PhoneNumber $phone): void
    {
        $this->name = $name;
        $this->phone = $phone;
    }

    /** Set a new base limit; what the customer already owes stays as it is. */
    public function changeCreditLimit(CreditLimit $creditLimit): void
    {
        $this->creditLimit = $creditLimit;
    }

    /** Stop selling on fiado to the customer, keeping the history and the balance. */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /** Let the customer buy on fiado again. */
    public function reactivate(): void
    {
        $this->active = true;
    }
}
