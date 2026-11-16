<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Infrastructure\Credit;

use DateTimeImmutable;
use Src\Credit\Customers\Application\UseCases\ListCustomers;
use Src\Sales\SaleDrafts\Application\Contracts\DraftCustomers;
use Src\Sales\SaleDrafts\Application\DTOs\DraftCustomer;
use Src\Shared\Domain\ValueObjects\PageRequest;

/** Lets the drafts read the customers of fiado through the use case of the credit, so they never import its domain. */
final readonly class CreditDraftCustomers implements DraftCustomers
{
    /** Receive the use case that lists the customers of the current negocio. */
    public function __construct(private ListCustomers $listCustomers) {}

    /** Every customer, page after page, with what they can buy on fiado at the moment. */
    public function all(DateTimeImmutable $at): array
    {
        $customers = [];
        $number = 1;

        do {
            $page = $this->listCustomers->execute(null, null, new PageRequest($number, PageRequest::MAX_SIZE));

            foreach ($page->items as $customer) {
                $customers[] = new DraftCustomer(
                    $customer->id->value,
                    $customer->name->value,
                    $customer->active,
                    $customer->balance,
                    $customer->availableCredit($at),
                );
            }

            $number++;
        } while (count($page->items) === PageRequest::MAX_SIZE);

        return $customers;
    }
}
