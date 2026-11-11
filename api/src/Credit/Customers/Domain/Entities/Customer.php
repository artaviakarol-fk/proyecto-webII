<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Entities;

use DateTimeImmutable;
use Src\Credit\Customers\Domain\Exceptions\CreditIncreaseAboveMaximumException;
use Src\Credit\Customers\Domain\Exceptions\CreditLimitExceededException;
use Src\Credit\Customers\Domain\Exceptions\CustomerInactiveException;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Credit\Customers\Domain\ValueObjects\CreditLimit;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerName;
use Src\Credit\Customers\Domain\ValueObjects\IncreaseReason;
use Src\Credit\Customers\Domain\ValueObjects\LimitIncrease;
use Src\Credit\Customers\Domain\ValueObjects\PhoneNumber;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * A trusted customer who may buy on fiado up to a limit.
 *
 * The balance can be read but has no setter: it only moves through the movements of fiado
 * (sales on credit and payments), which the database applies to the customer.
 *
 * The limit has two parts: the base limit the administration sets, and the exceptional increases
 * a cashier gives (US-304), which count only until they expire or are reverted. The increases never
 * change the base limit.
 */
final class Customer
{
    /**
     * Hold the full state of the customer.
     *
     * @param  list<LimitIncrease>  $increases  increases that were not reverted
     */
    private function __construct(
        public readonly CustomerId $id,
        public readonly NegocioId $negocioId,
        public private(set) CustomerName $name,
        public private(set) ?PhoneNumber $phone,
        public private(set) CreditLimit $creditLimit,
        public readonly Money $balance,
        public private(set) bool $active,
        private array $increases,
    ) {}

    /** Register a new customer of a negocio, active, owing nothing and without increases. */
    public static function register(
        CustomerId $id,
        NegocioId $negocioId,
        CustomerName $name,
        ?PhoneNumber $phone,
        CreditLimit $creditLimit,
    ): self {
        return new self($id, $negocioId, $name, $phone, $creditLimit, new Money(0), true, []);
    }

    /**
     * Rebuild a customer from its stored state.
     *
     * @param  list<LimitIncrease>  $increases  increases that were not reverted
     */
    public static function reconstitute(
        CustomerId $id,
        NegocioId $negocioId,
        CustomerName $name,
        ?PhoneNumber $phone,
        CreditLimit $creditLimit,
        Money $balance,
        bool $active,
        array $increases = [],
    ): self {
        return new self($id, $negocioId, $name, $phone, $creditLimit, $balance, $active, $increases);
    }

    /** Replace the contact data of the customer. */
    public function revise(CustomerName $name, ?PhoneNumber $phone): void
    {
        $this->name = $name;
        $this->phone = $phone;
    }

    /** Set a new base limit; what the customer already owes and the increases stay as they are. */
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

    /** The sum of the increases that count at a moment. */
    public function increasesAt(DateTimeImmutable $at): Money
    {
        return array_reduce(
            array_filter($this->increases, fn (LimitIncrease $increase): bool => $increase->countsAt($at)),
            fn (Money $sum, LimitIncrease $increase): Money => $sum->plus($increase->amount),
            new Money(0),
        );
    }

    /** The most the customer may owe at a moment: the base limit plus the increases that still count. */
    public function effectiveLimit(DateTimeImmutable $at): Money
    {
        return $this->creditLimit->amount->plus($this->increasesAt($at));
    }

    /** What the customer can still buy on fiado: the limit minus what they owe, never below zero. */
    public function availableCredit(DateTimeImmutable $at): Money
    {
        return new Money(max(0, $this->effectiveLimit($at)->cents - $this->balance->cents));
    }

    /** A sale goes on fiado only to an active customer, and only if what they owe plus the sale fits the limit. */
    public function assertCanBuyOnFiado(Money $sale, DateTimeImmutable $at): void
    {
        if (! $this->active) {
            throw CustomerInactiveException::create($this->name->value);
        }

        $limit = $this->effectiveLimit($at);

        if ($this->balance->cents + $sale->cents > $limit->cents) {
            throw CreditLimitExceededException::create($this->name->value, $sale, $this->balance, $limit, $this->availableCredit($at));
        }
    }

    /**
     * Give an exceptional increase to an active customer. The base limit plus every increase that
     * counts, this one included, can never pass the maximum of the negocio (₡120 000 by default).
     */
    public function grantIncrease(
        CreditIncreaseId $id,
        Money $amount,
        IncreaseReason $reason,
        string $userId,
        DateTimeImmutable $at,
        Money $maximum,
    ): CreditIncrease {
        if (! $this->active) {
            throw CustomerInactiveException::create($this->name->value);
        }

        $current = $this->effectiveLimit($at);
        $wouldBe = $current->plus($amount);

        if ($wouldBe->cents > $maximum->cents) {
            throw CreditIncreaseAboveMaximumException::create(
                $this->name->value,
                $amount,
                $wouldBe,
                $maximum,
                new Money(max(0, $maximum->cents - $current->cents)),
            );
        }

        $increase = CreditIncrease::grant($id, $this->negocioId, $this->id, $amount, $reason, $userId, $at);
        $this->increases[] = $increase->term();

        return $increase;
    }
}
