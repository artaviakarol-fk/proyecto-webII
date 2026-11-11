<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Exceptions\CustomerNotFoundException;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\Contracts\NegocioContext;

final readonly class DeactivateCustomer
{
    /** Receive the negocio of the operation and the repository. */
    public function __construct(
        private NegocioContext $context,
        private CustomerRepository $customers,
    ) {}

    /** Stop selling on fiado to a customer of the current negocio, keeping the history and the balance. */
    public function execute(string $customerId): void
    {
        $customer = $this->customers->find(new CustomerId($customerId), $this->context->current())
            ?? throw CustomerNotFoundException::create();

        $customer->deactivate();

        $this->customers->save($customer);
    }
}
