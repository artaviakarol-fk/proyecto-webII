<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\Contracts\CreditPaymentReader;
use Src\Credit\Customers\Application\DTOs\CreditPaymentReceipt;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

/** The abonos a customer has made, to review them or print a receipt again. */
final readonly class ListCustomerPayments
{
    /** Receive the negocio of the operation, the customers and the reader. */
    public function __construct(
        private NegocioContext $context,
        private CustomerRepository $customers,
        private CreditPaymentReader $reader,
    ) {}

    /**
     * One page of the abonos of a customer of the current negocio, the newest first.
     *
     * @return Page<CreditPaymentReceipt>
     */
    public function execute(string $customerId, PageRequest $page): Page
    {
        $negocioId = $this->context->current();
        $id = new CustomerId($customerId);

        $this->customers->find($id, $negocioId) ?? throw CustomerNotFoundException::create();

        return $this->reader->listForCustomer($id, $negocioId, $page);
    }
}
