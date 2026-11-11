<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class GetCustomer
{
    /** Receive the negocio of the operation and the repository. */
    public function __construct(
        private NegocioContext $context,
        private CustomerRepository $customers,
    ) {}

    /** One customer of the current negocio, with what they owe. */
    public function execute(string $customerId): Customer
    {
        return $this->customers->find(new CustomerId($customerId), $this->context->current())
            ?? throw CustomerNotFoundException::create();
    }
}
