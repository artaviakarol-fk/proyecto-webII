<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Credit\Customers\Application\DTOs\CreditPaymentReceipt;
use Src\Credit\Customers\Application\UseCases\GetCreditPaymentReceipt;
use Src\Credit\Customers\Application\UseCases\ListCustomerPayments;
use Src\Credit\Customers\Application\UseCases\ReceiveCreditPayment;
use Src\Credit\Customers\Domain\ValueObjects\Colones;
use Src\Credit\Customers\Infrastructure\Http\Requests\ReceiveCreditPaymentRequest;
use Src\Credit\Customers\Infrastructure\Http\Resources\CreditPaymentResource;
use Src\Credit\Customers\Infrastructure\Http\Resources\CustomerResource;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Infrastructure\Http\Requests\ListRequest;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class CreditPaymentController
{
    /**
     * Receive an abono: part of what the customer owes, or all of it. It never leaves the balance
     * negative. It answers the abono with its receipt link and the customer with what remains.
     *
     * @response 201 array{data: array{abono: CreditPaymentResource, cliente: CustomerResource}}
     */
    public function store(ReceiveCreditPaymentRequest $request, string $cliente, ReceiveCreditPayment $receive): JsonResponse
    {
        $received = $receive->execute($cliente, $request->amount(), $request->method(), now()->toDateTimeImmutable());

        return new JsonResponse(['data' => [
            'abono' => new CreditPaymentResource($received->receipt)->toArray($request),
            'cliente' => new CustomerResource($received->customer)->toArray($request),
        ]], Response::HTTP_CREATED);
    }

    /**
     * The abonos of a customer, the newest first.
     *
     * @response array{data: list<CreditPaymentResource>, meta: array{pagina: int, por_pagina: int, total: int, ultima_pagina: int}}
     */
    public function index(ListRequest $request, string $cliente, ListCustomerPayments $list): JsonResponse
    {
        $page = $list->execute($cliente, $request->page());

        return new JsonResponse([
            'data' => array_map(fn (CreditPaymentReceipt $receipt): array => new CreditPaymentResource($receipt)->toArray($request), $page->items),
            ...PageMeta::of($page),
        ]);
    }

    /** One abono. */
    public function show(string $abono, GetCreditPaymentReceipt $get): CreditPaymentResource
    {
        return new CreditPaymentResource($get->execute($abono));
    }

    /** The receipt of an abono as a printable page (text/html); the browser prints it or saves it as PDF. */
    public function receipt(string $abono, GetCreditPaymentReceipt $get): Response
    {
        $receipt = $get->execute($abono);

        return response()->view('credit::recibo', [
            'recibo' => $receipt,
            'monto' => Colones::format(Money::fromDecimal($receipt->amount)),
            'saldoAnterior' => Colones::format(Money::fromDecimal($receipt->balanceBefore)),
            'saldoRestante' => Colones::format(Money::fromDecimal($receipt->balanceAfter)),
            'fecha' => CarbonImmutable::parse($receipt->receivedAt)->translatedFormat('j \d\e F \d\e Y, g:i a'),
            'numero' => strtoupper(substr(str_replace('-', '', $receipt->id), -8)),
        ]);
    }
}
