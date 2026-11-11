<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Infrastructure\Persistence\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Credit\Customers\Application\Contracts\CreditPaymentReader;
use Src\Credit\Customers\Application\DTOs\CreditPaymentReceipt;
use Src\Credit\Customers\Domain\ValueObjects\CreditPaymentId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use stdClass;

/** Reads the abonos with the names the receipt needs: the negocio, the customer and the cashier. */
final readonly class DatabaseCreditPaymentReader implements CreditPaymentReader
{
    /** The receipt of one abono of the negocio. */
    public function find(CreditPaymentId $id, NegocioId $negocioId): ?CreditPaymentReceipt
    {
        $row = $this->query($negocioId)->where('a.id', $id->value)->first();

        return $row === null ? null : $this->receipt($row);
    }

    /** One page of the abonos of a customer, the newest first. */
    public function listForCustomer(CustomerId $customerId, NegocioId $negocioId, PageRequest $page): Page
    {
        $query = $this->query($negocioId)->where('a.cliente_id', $customerId->value);
        $total = $query->getCountForPagination();

        $rows = $total > $page->offset()
            ? $query->orderByDesc('a.created_at')->orderByDesc('a.id')->forPage($page->number, $page->size)->get()->all()
            : [];

        $receipts = [];

        foreach ($rows as $row) {
            $receipts[] = $this->receipt($row);
        }

        return new Page($receipts, $total, $page);
    }

    /** The abonos of the negocio with the names they need. */
    private function query(NegocioId $negocioId): Builder
    {
        return DB::table('abonos as a')
            ->join('clientes as c', 'c.id', '=', 'a.cliente_id')
            ->join('users as u', 'u.id', '=', 'a.usuario_id')
            ->join('negocios as n', 'n.id', '=', 'a.negocio_id')
            ->where('a.negocio_id', $negocioId->value)
            ->select([
                'a.id', 'n.nombre as negocio_nombre', 'a.cliente_id', 'c.nombre as cliente_nombre', 'a.monto', 'a.metodo_pago',
                'a.saldo_anterior', 'a.saldo_restante', 'a.usuario_id', 'u.nombre as usuario_nombre', 'a.created_at',
            ]);
    }

    /** Turn a row into the receipt. */
    private function receipt(stdClass $row): CreditPaymentReceipt
    {
        return new CreditPaymentReceipt(
            (string) $row->id,
            (string) $row->negocio_nombre,
            (string) $row->cliente_id,
            (string) $row->cliente_nombre,
            (string) $row->monto,
            (string) $row->metodo_pago,
            (string) $row->saldo_anterior,
            (string) $row->saldo_restante,
            (string) $row->usuario_id,
            (string) $row->usuario_nombre,
            CarbonImmutable::parse((string) $row->created_at)->setTimezone((string) config('app.timezone'))->toIso8601String(),
        );
    }
}
