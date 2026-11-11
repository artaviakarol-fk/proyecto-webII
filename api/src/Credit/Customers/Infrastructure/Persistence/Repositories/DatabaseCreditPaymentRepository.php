<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Credit\Customers\Domain\Contracts\CreditPaymentRepository;
use Src\Credit\Customers\Domain\Entities\CreditPayment;
use Src\Credit\Customers\Domain\ValueObjects\CreditPaymentId;

final readonly class DatabaseCreditPaymentRepository implements CreditPaymentRepository
{
    /** Generate a time-ordered UUID for a new abono. */
    public function nextId(): CreditPaymentId
    {
        return new CreditPaymentId((string) Str::uuid7());
    }

    /** Insert the abono; the application role cannot change it afterwards. */
    public function add(CreditPayment $payment): void
    {
        DB::table('abonos')->insert([
            'id' => $payment->id->value,
            'negocio_id' => $payment->negocioId->value,
            'cliente_id' => $payment->customerId->value,
            'monto' => $payment->amount->toDecimal(),
            'metodo_pago' => $payment->method,
            'saldo_anterior' => $payment->balanceBefore->toDecimal(),
            'saldo_restante' => $payment->balanceAfter->toDecimal(),
            'usuario_id' => $payment->receivedBy,
            'created_at' => $payment->receivedAt,
        ]);
    }
}
