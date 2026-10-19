<?php

declare(strict_types=1);

namespace Src\Inventory\Receipts\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Inventory\Receipts\Application\UseCases\GetGoodsReceipt;
use Src\Inventory\Receipts\Application\UseCases\RegisterGoodsReceipt;
use Src\Inventory\Receipts\Infrastructure\Http\Requests\RegisterReceiptRequest;
use Src\Inventory\Receipts\Infrastructure\Http\Resources\ReceiptResource;

final readonly class GoodsReceiptController
{
    /** Register a delivery of goods and add it to the stock. */
    public function store(RegisterReceiptRequest $request, RegisterGoodsReceipt $registerReceipt): JsonResponse
    {
        $receipt = $registerReceipt->execute($request->toCommand());

        return new ReceiptResource($receipt)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$receipt->id);
    }

    /** Show a delivery of the negocio; one of another negocio answers 404. */
    public function show(string $entrada, GetGoodsReceipt $getReceipt): ReceiptResource
    {
        return new ReceiptResource($getReceipt->execute($entrada));
    }
}
