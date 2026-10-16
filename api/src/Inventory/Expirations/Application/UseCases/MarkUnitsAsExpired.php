<?php

declare(strict_types=1);

namespace Src\Inventory\Expirations\Application\UseCases;

use Src\Inventory\Expirations\Application\DTOs\MarkedUnits;
use Src\Inventory\Expirations\Application\DTOs\MarkExpiredCommand;
use Src\Inventory\Expirations\Domain\Contracts\ExpiredUnitsRepository;
use Src\Inventory\Expirations\Domain\Entities\ExpiredUnits;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiredQuantity;
use Src\Inventory\Expirations\Domain\ValueObjects\ExpiryReason;
use Src\Inventory\Products\Application\UseCases\GetAvailability;
use Src\Inventory\Products\Application\UseCases\GetProduct;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class MarkUnitsAsExpired
{
    /** Receive the ports of the negocio, the account, the transaction and the expired units, and the product use cases. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private ExpiredUnitsRepository $expiredUnits,
        private GetProduct $getProduct,
        private GetAvailability $getAvailability,
    ) {}

    /**
     * Mark units of a product of the current negocio as expired. They stay in the stock, but they leave the
     * sellable stock, so the cashier is warned without blocking the whole product.
     */
    public function execute(MarkExpiredCommand $command): MarkedUnits
    {
        $this->getProduct->execute($command->productId);

        $units = ExpiredUnits::mark(
            $this->expiredUnits->nextId(),
            $this->context->current(),
            new ProductId($command->productId),
            new ExpiredQuantity($command->quantity),
            new ExpiryReason($command->reason),
            $this->user->id(),
            $command->now,
        );

        $this->transaction->run(fn () => $this->expiredUnits->save($units));

        [, $availability] = $this->getAvailability->execute($command->productId);

        return new MarkedUnits(
            $units->id->value,
            $units->productId->value,
            $units->quantity->value,
            $units->reason->value,
            $units->status->value,
            $units->markedBy,
            $command->now->format(DATE_ATOM),
            $availability->stock->units,
            $availability->expiredUnits,
            $availability->sellable,
        );
    }
}
