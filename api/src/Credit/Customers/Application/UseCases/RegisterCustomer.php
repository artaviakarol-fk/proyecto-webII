<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\DTOs\CustomerData;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class RegisterCustomer
{
    use BuildsCustomerData;

    /** Receive the negocio of the operation, the repository and the base limit of fiado (₡90 000). */
    public function __construct(
        private NegocioContext $context,
        private CustomerRepository $customers,
        private string $baseLimit,
    ) {}

    /** Register a trusted customer of the current negocio, owing nothing. */
    public function execute(CustomerData $data): Customer
    {
        $customer = Customer::register(
            $this->customers->nextId(),
            $this->context->current(),
            $this->name($data),
            $this->phone($data),
            $this->creditLimit($data),
        );

        if (! $data->active) {
            $customer->deactivate();
        }

        $this->customers->save($customer);

        return $customer;
    }
}
