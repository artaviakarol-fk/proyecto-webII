<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Application\UseCases;

use Src\Credit\Customers\Application\DTOs\CustomerData;
use Src\Credit\Customers\Domain\ValueObjects\CreditLimit;
use Src\Credit\Customers\Domain\ValueObjects\CustomerName;
use Src\Credit\Customers\Domain\ValueObjects\PhoneNumber;
use Src\Shared\Domain\ValueObjects\Money;

/** Turn the data of a request into value objects, checking the limit against the base limit. */
trait BuildsCustomerData
{
    /** Name of the customer. */
    private function name(CustomerData $data): CustomerName
    {
        return new CustomerName($data->name);
    }

    /** Phone of the customer, if any. */
    private function phone(CustomerData $data): ?PhoneNumber
    {
        return $data->phone === null || trim($data->phone) === '' ? null : new PhoneNumber($data->phone);
    }

    /** Limit sent, or the base limit when none was sent; never above the base limit. */
    private function creditLimit(CustomerData $data): CreditLimit
    {
        $maximum = Money::fromDecimal($this->baseLimit);

        return CreditLimit::upTo($data->creditLimit === null ? $maximum : Money::fromDecimal($data->creditLimit), $maximum);
    }
}
