<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Inventory\Receipts\Application\Contracts\ReceiptReader;
use Src\Inventory\Receipts\Domain\Contracts\GoodsReceiptRepository;
use Src\Inventory\Receipts\Infrastructure\Persistence\Repositories\DatabaseReceiptReader;
use Src\Inventory\Receipts\Infrastructure\Persistence\Repositories\EloquentGoodsReceiptRepository;

final class ReceiptsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GoodsReceiptRepository::class => EloquentGoodsReceiptRepository::class,
        ReceiptReader::class => DatabaseReceiptReader::class,
    ];
}
