<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use DateTimeImmutable;
use Src\Credit\Customers\Application\Contracts\CreditIncreaseReader;
use Src\Credit\Customers\Application\DTOs\CreditIncreaseSummary;
use Src\Credit\Customers\Domain\Contracts\CreditIncreaseRepository;
use Src\Credit\Customers\Domain\Exceptions\CreditIncreaseNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

/** The administration takes back an increase that still counts; the record stays with who reverted it and when. */
final readonly class RevertCreditIncrease
{
    /** Receive the ports of the negocio, the administrator, the transaction and the increases. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private CreditIncreaseRepository $increases,
        private CreditIncreaseReader $reader,
    ) {}

    /** Revert the increase; what the customer already owes stays as it is. */
    public function execute(string $increaseId, DateTimeImmutable $at): CreditIncreaseSummary
    {
        $negocioId = $this->context->current();
        $id = new CreditIncreaseId($increaseId);

        return $this->transaction->run(function () use ($id, $at, $negocioId): CreditIncreaseSummary {
            $increase = $this->increases->findForUpdate($id, $negocioId) ?? throw CreditIncreaseNotFoundException::create();
            $increase->revert($this->user->id(), $at);
            $this->increases->save($increase);

            return $this->reader->find($id, $negocioId, $at) ?? throw CreditIncreaseNotFoundException::create();
        });
    }
}
