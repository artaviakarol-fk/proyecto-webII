<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use DateTimeImmutable;
use Src\Credit\Customers\Application\Contracts\CreditIncreaseReader;
use Src\Credit\Customers\Application\DTOs\GrantedIncrease;
use Src\Credit\Customers\Domain\Contracts\CreditIncreaseRepository;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Exceptions\CreditIncreaseNotFoundException;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\IncreaseReason;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\Money;

/** US-304: the cashier increases the limit of a customer in an emergency, with a reason, until the end of the day. */
final readonly class GrantCreditIncrease
{
    /** Receive the ports of the negocio, the cashier, the transaction, the customers and the increases, and the maximum limit (₡120 000). */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private CustomerRepository $customers,
        private CreditIncreaseRepository $increases,
        private CreditIncreaseReader $reader,
        private string $maxLimit,
    ) {}

    /**
     * Record the increase in one transaction. The row of the customer is locked first, so two
     * increases given at the same time are both counted against the maximum.
     */
    public function execute(string $customerId, string $amount, string $reason, DateTimeImmutable $at): GrantedIncrease
    {
        $negocioId = $this->context->current();

        return $this->transaction->run(function () use ($customerId, $amount, $reason, $at, $negocioId): GrantedIncrease {
            $customer = $this->customers->findForUpdate(new CustomerId($customerId), $negocioId)
                ?? throw CustomerNotFoundException::create();

            $increase = $customer->grantIncrease(
                $this->increases->nextId(),
                Money::fromDecimal($amount),
                new IncreaseReason($reason),
                $this->user->id(),
                $at,
                Money::fromDecimal($this->maxLimit),
            );
            $this->increases->save($increase);

            $summary = $this->reader->find($increase->id, $negocioId, $at) ?? throw CreditIncreaseNotFoundException::create();

            return new GrantedIncrease($summary, $customer);
        });
    }
}
