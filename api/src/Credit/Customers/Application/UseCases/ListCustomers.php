<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Domain\Contracts\CustomerRepository;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final readonly class ListCustomers
{
    /** Receive the negocio of the operation and the repository. */
    public function __construct(
        private NegocioContext $context,
        private CustomerRepository $customers,
    ) {}

    /**
     * One page of the customers of the current negocio, optionally searching by name or phone
     * and optionally only the active or inactive ones.
     *
     * @return Page<Customer>
     */
    public function execute(?string $term, ?bool $active, PageRequest $page): Page
    {
        $term = $term === null || trim($term) === '' ? null : trim($term);

        return $this->customers->search($this->context->current(), $term, $active, $page);
    }
}
