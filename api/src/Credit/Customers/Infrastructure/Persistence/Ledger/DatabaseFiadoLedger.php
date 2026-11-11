<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Ledger;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Credit\Customers\Domain\Contracts\FiadoLedger;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\FiadoCharge;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

/** Writes the movements of fiado; the trigger of movimientos_fiado moves the balance of the customer in the same statement. */
final readonly class DatabaseFiadoLedger implements FiadoLedger
{
    /** Insert the charge of a sale. */
    public function recordCharge(NegocioId $negocioId, FiadoCharge $charge, string $userId): void
    {
        $this->insert($negocioId, $charge, 'cargo', $userId);
    }

    /** The charge of the sale, unless it was already given back. */
    public function pendingChargeOf(NegocioId $negocioId, string $saleId): ?FiadoCharge
    {
        $movements = DB::table('movimientos_fiado')
            ->where('negocio_id', $negocioId->value)
            ->where('venta_id', $saleId)
            ->get(['tipo', 'cliente_id', 'monto']);

        $charge = $movements->firstWhere('tipo', 'cargo');

        if ($charge === null || $movements->contains('tipo', 'anulacion')) {
            return null;
        }

        return new FiadoCharge(new CustomerId((string) $charge->cliente_id), $saleId, Money::fromDecimal((string) $charge->monto));
    }

    /** Insert the movement that gives the charge back. */
    public function recordCancellation(NegocioId $negocioId, FiadoCharge $charge, string $userId): void
    {
        $this->insert($negocioId, $charge, 'anulacion', $userId);
    }

    /** One row of the ledger. */
    private function insert(NegocioId $negocioId, FiadoCharge $charge, string $type, string $userId): void
    {
        DB::table('movimientos_fiado')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $negocioId->value,
            'cliente_id' => $charge->customerId->value,
            'tipo' => $type,
            'monto' => $charge->amount->toDecimal(),
            'venta_id' => $charge->saleId,
            'usuario_id' => $userId,
        ]);
    }
}
