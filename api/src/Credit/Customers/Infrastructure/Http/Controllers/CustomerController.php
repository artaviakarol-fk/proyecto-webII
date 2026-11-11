<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Src\Credit\Customers\Application\UseCases\DeactivateCustomer;
use Src\Credit\Customers\Application\UseCases\GetCustomer;
use Src\Credit\Customers\Application\UseCases\ListCustomers;
use Src\Credit\Customers\Application\UseCases\RegisterCustomer;
use Src\Credit\Customers\Application\UseCases\UpdateCustomer;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Credit\Customers\Infrastructure\Http\Requests\CustomerRequest;
use Src\Credit\Customers\Infrastructure\Http\Requests\ListCustomersRequest;
use Src\Credit\Customers\Infrastructure\Http\Resources\CustomerResource;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

final readonly class CustomerController
{
    /**
     * List the customers of the negocio by pages, with what each one owes.
     *
     * @response array{data: list<CustomerResource>, meta: array{pagina: int, por_pagina: int, total: int, ultima_pagina: int}}
     */
    public function index(ListCustomersRequest $request, ListCustomers $listCustomers): JsonResponse
    {
        $page = $listCustomers->execute($request->term(), $request->active(), $request->page());

        return new JsonResponse([
            'data' => array_map(fn (Customer $customer): array => new CustomerResource($customer)->toArray($request), $page->items),
            ...PageMeta::of($page),
        ]);
    }

    /** Show one customer of the negocio and its balance; a customer of another negocio answers 404. */
    public function show(string $cliente, GetCustomer $getCustomer): CustomerResource
    {
        return new CustomerResource($getCustomer->execute($cliente));
    }

    /** Register a trusted customer; without a limit it gets the base limit (₡90 000). */
    public function store(CustomerRequest $request, RegisterCustomer $registerCustomer): JsonResponse
    {
        $customer = $registerCustomer->execute($request->toData());

        return new CustomerResource($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$customer->id->value);
    }

    /** Replace the data and the limit of a customer; the balance never changes here. */
    public function update(CustomerRequest $request, string $cliente, UpdateCustomer $updateCustomer): CustomerResource
    {
        return new CustomerResource($updateCustomer->execute($cliente, $request->toData()));
    }

    /** Deactivate a customer: it can no longer buy on fiado, but its history and balance stay. */
    public function destroy(string $cliente, DeactivateCustomer $deactivateCustomer): Response
    {
        $deactivateCustomer->execute($cliente);

        return response()->noContent();
    }
}
