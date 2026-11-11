<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\DTOs\CustomerData;
use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class UpdateCustomer
{
    use BuildsCustomerData;

    /** Receive the negocio of the operation, the repository and the base limit of fiado (₡90 000). */
    public function __construct(
        private NegocioContext $context,
        private CustomerRepository $customers,
        private string $baseLimit,
    ) {}

    /** Replace the data of a customer of the current negocio; the balance stays as it is. */
    public function execute(string $customerId, CustomerData $data): Customer
    {
        $customer = $this->customers->find(new CustomerId($customerId), $this->context->current())
            ?? throw CustomerNotFoundException::create();

        $customer->revise($this->name($data), $this->phone($data));
        $customer->changeCreditLimit($this->creditLimit($data));
        $data->active ? $customer->reactivate() : $customer->deactivate();

        $this->customers->save($customer);

        return $customer;
    }
}
