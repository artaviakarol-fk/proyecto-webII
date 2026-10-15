<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Application\UseCases;

use DateTimeImmutable;
use Src\Inventory\Products\Application\DTOs\ProductData;
use Src\Inventory\Products\Application\UseCases\CreateProduct;
use Src\Inventory\Receipts\Application\DTOs\ReceiptDetails;
use Src\Inventory\Receipts\Application\DTOs\ReceiptLineData;
use Src\Inventory\Receipts\Application\DTOs\RegisterReceiptCommand;
use Src\Inventory\Receipts\Domain\Contracts\GoodsReceiptRepository;
use Src\Inventory\Receipts\Domain\Entities\GoodsReceipt;
use Src\Inventory\Receipts\Domain\ValueObjects\ReceiptLine;
use Src\Inventory\Receipts\Domain\ValueObjects\Supplier;
use Src\Inventory\Shared\Domain\ValueObjects\Money;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class RegisterGoodsReceipt
{
    /** Receive the ports of the negocio, the account, the transaction, the receipts and the product creation. */
    public function __construct(
        private NegocioContext $context,
        private CurrentUser $user,
        private TransactionRunner $transaction,
        private GoodsReceiptRepository $receipts,
        private CreateProduct $createProduct,
        private GetGoodsReceipt $getReceipt,
    ) {}

    /**
     * Register a delivery in one transaction: create the new products, add every line to the stock and
     * record who did it. If any line fails, nothing is stored.
     */
    public function execute(RegisterReceiptCommand $command): ReceiptDetails
    {
        $receipt = $this->transaction->run(function () use ($command): GoodsReceipt {
            $receipt = GoodsReceipt::register(
                $this->receipts->nextId(),
                $this->context->current(),
                new Supplier($command->supplier),
                $this->date($command->date),
                $command->now,
                $this->user->id(),
                array_map($this->line(...), $command->lines),
            );

            $this->receipts->save($receipt);

            return $receipt;
        });

        return $this->getReceipt->execute($receipt->id->value);
    }

    /** Build a line, creating its product first when the line brings a new one. */
    private function line(ReceiptLineData $data): ReceiptLine
    {
        $unitCost = Money::fromDecimal($data->unitCost);
        $productId = $data->newProduct === null
            ? $data->productId
            : $this->createProduct->execute($this->withoutStock($data->newProduct, $unitCost))->id->value;

        return new ReceiptLine(new ProductId((string) $productId), $data->quantity, $unitCost);
    }

    /** A product created from a receipt starts at zero units: its stock comes from the line itself. */
    private function withoutStock(ProductData $product, Money $unitCost): ProductData
    {
        return new ProductData(
            $product->code,
            $product->name,
            $product->category,
            $product->salePrice,
            $unitCost->toDecimal(),
            0,
            $product->perishable,
            $product->liquor,
        );
    }

    /** Read the date of the delivery, written as YYYY-MM-DD. */
    private function date(string $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $date)
            ?: throw new InvalidValueException('inventory.receipt_date_invalid');
    }
}
