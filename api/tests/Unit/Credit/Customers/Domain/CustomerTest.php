<?php

declare(strict_types=1);

namespace Tests\Unit\Credit\Customers\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Credit\Customers\Domain\Exceptions\CreditLimitExceededException;
use Src\Credit\Customers\Domain\Exceptions\CustomerInactiveException;
use Src\Credit\Customers\Domain\ValueObjects\CreditLimit;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerName;
use Src\Credit\Customers\Domain\ValueObjects\PhoneNumber;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class CustomerTest extends TestCase
{
    /** A new customer is active and owes nothing. */
    public function test_new_customer_is_active_and_owes_nothing(): void
    {
        $customer = $this->customer();

        $this->assertTrue($customer->active);
        $this->assertTrue($customer->balance->isZero());
        $this->assertSame(9_000_000, $customer->creditLimit->amount->cents);
    }

    /** The limit may go from zero up to the base limit, both included. */
    public function test_limit_goes_from_zero_up_to_the_maximum(): void
    {
        $maximum = Money::fromDecimal('90000');

        $this->assertSame(0, CreditLimit::upTo(new Money(0), $maximum)->amount->cents);
        $this->assertSame(9_000_000, CreditLimit::upTo($maximum, $maximum)->amount->cents);
    }

    /** One céntimo above the base limit is rejected. */
    public function test_limit_above_the_maximum_is_rejected(): void
    {
        try {
            CreditLimit::upTo(new Money(9_000_001), Money::fromDecimal('90000'));
            $this->fail('The limit should be rejected.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('credit.limit_above_maximum', $exception->translationKey());
            $this->assertSame(['max' => '₡90 000'], $exception->parameters());
        }
    }

    /** Changing the limit, the data or the state never touches what the customer owes. */
    public function test_changes_never_touch_the_balance(): void
    {
        $customer = Customer::reconstitute(
            new CustomerId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d01'),
            new NegocioId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d02'),
            new CustomerName('Ana'),
            null,
            CreditLimit::reconstitute(Money::fromDecimal('90000')),
            Money::fromDecimal('15000'),
            true,
        );

        $customer->changeCreditLimit(CreditLimit::upTo(Money::fromDecimal('10000'), Money::fromDecimal('90000')));
        $customer->revise(new CustomerName('Ana Mora'), new PhoneNumber('8888-0001'));
        $customer->deactivate();

        $this->assertSame(1_500_000, $customer->balance->cents);
        $this->assertSame(1_000_000, $customer->creditLimit->amount->cents);
        $this->assertSame('Ana Mora', $customer->name->value);
        $this->assertFalse($customer->active);

        $customer->reactivate();
        $this->assertTrue($customer->active);
    }

    /** A sale fits while what is owed plus the sale stays within the limit, the limit itself included. */
    public function test_sale_fits_up_to_the_limit(): void
    {
        $customer = $this->owing('80000');

        $customer->assertCanBuyOnFiado(Money::fromDecimal('10000'));
        $this->assertSame(1_000_000, $customer->availableCredit()->cents);

        $this->expectException(CreditLimitExceededException::class);
        $customer->assertCanBuyOnFiado(Money::fromDecimal('10000.01'));
    }

    /** The message tells what the sale is, what is owed, the limit and what is left. */
    public function test_limit_message_has_what_the_cashier_needs(): void
    {
        try {
            $this->owing('80000')->assertCanBuyOnFiado(Money::fromDecimal('12500.50'));
            $this->fail('The sale should not fit.');
        } catch (CreditLimitExceededException $exception) {
            $this->assertSame([
                'customer' => 'Ana',
                'sale' => '₡12 500,50',
                'balance' => '₡80 000',
                'limit' => '₡90 000',
                'available' => '₡10 000',
            ], $exception->parameters());
        }
    }

    /** An inactive customer cannot buy on fiado, whatever they owe. */
    public function test_inactive_customer_cannot_buy_on_fiado(): void
    {
        $customer = $this->owing('0');
        $customer->deactivate();

        $this->expectException(CustomerInactiveException::class);
        $customer->assertCanBuyOnFiado(new Money(100));
    }

    /** Valid phone numbers. */
    #[DataProvider('validPhones')]
    public function test_valid_phone_numbers(string $phone): void
    {
        $this->assertSame($phone, new PhoneNumber(" {$phone} ")->value);
    }

    /**
     * Phone numbers written the way people write them.
     *
     * @return array<string, array{string}>
     */
    public static function validPhones(): array
    {
        return ['con guion' => ['8888-0001'], 'solo dígitos' => ['88880001'], 'internacional' => ['+506 8888 0001']];
    }

    /** Invalid names and phones are rejected. */
    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(callable $build, string $key): void
    {
        try {
            $build();
            $this->fail('The value should be rejected.');
        } catch (InvalidValueException $exception) {
            $this->assertSame($key, $exception->translationKey());
        }
    }

    /**
     * Builders of invalid values and the message they raise.
     *
     * @return array<string, array{callable, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'nombre vacío' => [fn () => new CustomerName('   '), 'credit.name_invalid'],
            'nombre largo' => [fn () => new CustomerName(str_repeat('a', 121)), 'credit.name_invalid'],
            'teléfono con letras' => [fn () => new PhoneNumber('8888-ABCD'), 'credit.phone_invalid'],
            'teléfono corto' => [fn () => new PhoneNumber('123'), 'credit.phone_invalid'],
        ];
    }

    /** Ana, with the base limit, owing an amount. */
    private function owing(string $balance): Customer
    {
        return Customer::reconstitute(
            new CustomerId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d01'),
            new NegocioId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d02'),
            new CustomerName('Ana'),
            null,
            CreditLimit::reconstitute(Money::fromDecimal('90000')),
            Money::fromDecimal($balance),
            true,
        );
    }

    /** A new customer with the base limit. */
    private function customer(): Customer
    {
        return Customer::register(
            new CustomerId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d01'),
            new NegocioId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d02'),
            new CustomerName('Doña María'),
            new PhoneNumber('8888-0001'),
            CreditLimit::upTo(Money::fromDecimal('90000'), Money::fromDecimal('90000')),
        );
    }
}
