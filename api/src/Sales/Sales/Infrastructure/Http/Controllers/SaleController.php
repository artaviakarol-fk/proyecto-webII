<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Sales\Sales\Application\UseCases\CancelSale;
use Src\Sales\Sales\Application\UseCases\GetSale;
use Src\Sales\Sales\Application\UseCases\OpenSale;
use Src\Sales\Sales\Application\UseCases\PaySale;
use Src\Sales\Sales\Application\UseCases\ReviseSale;
use Src\Sales\Sales\Infrastructure\Http\Requests\SaleRequest;
use Src\Sales\Sales\Infrastructure\Http\Resources\SaleResource;

final readonly class SaleController
{
    /** Open a cash sale and take its units out of the stock. */
    public function store(SaleRequest $request, OpenSale $openSale): JsonResponse
    {
        $sale = $openSale->execute($request->toLines(), $request->buyerIdNumber());

        return new SaleResource($sale)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$sale->id);
    }

    /** Show a sale of the negocio; one of another negocio answers 404. */
    public function show(string $venta, GetSale $getSale): SaleResource
    {
        return new SaleResource($getSale->execute($venta));
    }

    /** Replace the lines of an open sale. */
    public function update(string $venta, SaleRequest $request, ReviseSale $reviseSale): SaleResource
    {
        return new SaleResource($reviseSale->execute($venta, $request->toLines(), $request->buyerIdNumber()));
    }

    /** Close an open sale as paid in cash. */
    public function pay(string $venta, PaySale $paySale): SaleResource
    {
        return new SaleResource($paySale->execute($venta));
    }

    /** Cancel a sale and give its units back to the stock. */
    public function cancel(string $venta, CancelSale $cancelSale): SaleResource
    {
        return new SaleResource($cancelSale->execute($venta));
    }
}
