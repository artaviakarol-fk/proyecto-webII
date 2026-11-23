<?php

declare(strict_types=1);

namespace Src\Inventory\Reservations\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Src\Inventory\Reservations\Application\DTOs\ReservationDetails;
use Src\Inventory\Reservations\Application\UseCases\CancelReservation;
use Src\Inventory\Reservations\Application\UseCases\ListMyReservations;
use Src\Inventory\Reservations\Application\UseCases\ReserveProduct;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaCancelada;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaCreada;
use Src\Inventory\Reservations\Infrastructure\Http\Requests\ReserveRequest;
use Src\Inventory\Reservations\Infrastructure\Http\Resources\ReservationResource;

final readonly class ReservationController
{
    /**
     * List the reservas the cliente still holds and what they add up to.
     *
     * A reserva that expired is not listed any more: its units are free again.
     *
     * @response array{data: list<ReservationResource>, total: float|int}
     */
    public function index(Request $request, ListMyReservations $listReservations): JsonResponse
    {
        $reservations = $listReservations->execute(now()->toDateTimeImmutable());

        return new JsonResponse([
            'data' => array_map(fn (ReservationDetails $details): array => new ReservationResource($details)->toArray($request), $reservations),
            'total' => array_sum(array_map(fn (ReservationDetails $details): int => $details->subtotal()->cents, $reservations)) / 100,
        ]);
    }

    /**
     * Hold units of a product for a while, before paying.
     *
     * The units are kept for the cliente for 15 minutes: nobody else can reserve or buy them. Reserving the same
     * product again replaces the previous reserva. When the product does not have that many units free it answers
     * 409 and says how many are left.
     */
    public function store(ReserveRequest $request, ReserveProduct $reserveProduct): JsonResponse
    {
        $reservation = $reserveProduct->execute($request->toCommand());

        // The agenda of the staff learns about it right away; it is sent after the commit of the request.
        ReservaCreada::dispatch(
            $reservation->id,
            $this->negocioOf($request),
            $reservation->productId,
            $reservation->quantity,
            $reservation->expiresAt->format(DATE_ATOM),
        );

        return new ReservationResource($reservation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** Let go a reserva: its units are free again. A reserva of somebody else, or one already expired, answers 404. */
    public function destroy(Request $request, string $reserva, CancelReservation $cancelReservation): Response
    {
        $reservaId = mb_strtolower($reserva);
        $cancelReservation->execute($reservaId, now()->toDateTimeImmutable());

        ReservaCancelada::dispatch($reservaId, $this->negocioOf($request));

        return response()->noContent();
    }

    /** The negocio of the account behind the token: never one sent by the browser. */
    private function negocioOf(Request $request): string
    {
        return (string) $request->user()?->getAttribute('negocio_id');
    }
}
