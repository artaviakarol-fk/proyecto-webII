<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Infrastructure\Http\Controllers;

use Src\Sales\SaleDrafts\Application\UseCases\DraftSaleFromText;
use Src\Sales\SaleDrafts\Infrastructure\Http\Requests\DraftSaleRequest;
use Src\Sales\SaleDrafts\Infrastructure\Http\Resources\SaleDraftResource;

final readonly class SaleDraftController
{
    /**
     * Turn what the cashier wrote into a draft of a sale, checked against the catalog, the stock and the
     * limit of fiado. It never registers the sale: the cashier confirms it with POST /ventas (and
     * POST /ventas/{id}/fiado on fiado). If the AI provider fails it answers 503 and the manual flow keeps working.
     */
    public function __invoke(DraftSaleRequest $request, DraftSaleFromText $draft): SaleDraftResource
    {
        return new SaleDraftResource($draft->execute($request->text(), now()->toDateTimeImmutable()));
    }
}
