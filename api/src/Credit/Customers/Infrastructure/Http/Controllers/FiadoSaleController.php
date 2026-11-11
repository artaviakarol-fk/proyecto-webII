<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Http\Controllers;

use Src\Credit\Customers\Application\UseCases\SellOnFiado;
use Src\Credit\Customers\Infrastructure\Http\Requests\SellOnFiadoRequest;
use Src\Credit\Customers\Infrastructure\Http\Resources\FiadoSaleResource;

final readonly class FiadoSaleController
{
    /**
     * Close an open sale on fiado: its total goes to what the customer owes. If it does not fit the
     * limit of the customer, it answers 409 with what they owe and what is left, and the sale stays open.
     */
    public function store(SellOnFiadoRequest $request, string $venta, SellOnFiado $sellOnFiado): FiadoSaleResource
    {
        return new FiadoSaleResource($sellOnFiado->execute($venta, $request->customerId()));
    }
}
