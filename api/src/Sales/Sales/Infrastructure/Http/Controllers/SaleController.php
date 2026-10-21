<?php

declare(strict_types=1);

namespace Src\Sales\Sales\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Sales\Sales\Application\DTOs\SaleSummary;
use Src\Sales\Sales\Application\UseCases\CancelSale;
use Src\Sales\Sales\Application\UseCases\GetSale;
use Src\Sales\Sales\Application\UseCases\ListSales;
use Src\Sales\Sales\Application\UseCases\OpenSale;
use Src\Sales\Sales\Application\UseCases\PaySale;
use Src\Sales\Sales\Application\UseCases\ReviseSale;
use Src\Sales\Sales\Infrastructure\Http\Requests\ListSalesRequest;
use Src\Sales\Sales\Infrastructure\Http\Requests\SaleRequest;
use Src\Sales\Sales\Infrastructure\Http\Resources\SaleResource;
use Src\Sales\Sales\Infrastructure\Http\Resources\SaleSummaryResource;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class SaleController
{
    /**
     * List the sales of the negocio by pages, newest first, optionally by status and day.
     *
     * @response array{data: list<SaleSummaryResource>, meta: array{pagina: int, por_pagina: int, total: int, ultima_pagina: int}}
     */
    public function index(ListSalesRequest $request, ListSales $listSales): JsonResponse
    {
        $page = $listSales->execute($request->filter(), $request->page());

        return new JsonResponse([
            'data' => array_map(fn (SaleSummary $sale): array => new SaleSummaryResource($sale)->toArray($request), $page->items),
            ...PageMeta::of($page),
        ]);
    }

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
