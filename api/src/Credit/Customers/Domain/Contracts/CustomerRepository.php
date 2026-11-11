<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Contracts;

use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

interface CustomerRepository
{
    /** Generate the identity for a new customer. */
    public function nextId(): CustomerId;

    /** Persist a new or modified customer; the balance is never written from here. */
    public function save(Customer $customer): void;

    /** Find a customer of the negocio, active or not. */
    public function find(CustomerId $id, NegocioId $negocioId): ?Customer;

    /**
     * One page of the customers of the negocio ordered by name, optionally matching a name or phone
     * and optionally only the active (true) or inactive (false) ones.
     *
     * @return Page<Customer>
     */
    public function search(NegocioId $negocioId, ?string $term, ?bool $active, PageRequest $page): Page;
}
