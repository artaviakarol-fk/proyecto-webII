<?php

declare(strict_types=1);

namespace Tests\Unit\Credit\Customers\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Credit\Customers\Domain\Entities\CreditIncrease;
use Src\Credit\Customers\Domain\Entities\Customer;
use Src\Credit\Customers\Domain\Exceptions\CreditIncreaseAboveMaximumException;
use Src\Credit\Customers\Domain\Exceptions\CreditIncreaseNotRevertibleException;
use Src\Credit\Customers\Domain\Exceptions\CreditLimitExceededException;
use Src\Credit\Customers\Domain\Exceptions\CustomerInactiveException;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Credit\Customers\Domain\ValueObjects\CreditLimit;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerName;
use Src\Credit\Customers\Domain\ValueObjects\IncreaseReason;
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

        $customer->assertCanBuyOnFiado(Money::fromDecimal('10000'), $this->now());
        $this->assertSame(1_000_000, $customer->availableCredit($this->now())->cents);

        $this->expectException(CreditLimitExceededException::class);
        $customer->assertCanBuyOnFiado(Money::fromDecimal('10000.01'), $this->now());
    }

    /** The message tells what the sale is, what is owed, the limit and what is left. */
    public function test_limit_message_has_what_the_cashier_needs(): void
    {
        try {
            $this->owing('80000')->assertCanBuyOnFiado(Money::fromDecimal('12500.50'), $this->now());
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
        $customer->assertCanBuyOnFiado(new Money(100), $this->now());
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

    /** Increases add to the limit until midnight in Costa Rica, can be stacked and never pass the maximum. */
    public function test_increases_add_to_the_limit_until_the_end_of_the_day(): void
    {
        $customer = $this->owing('85000');
        $at = new DateTimeImmutable('2026-11-11 15:30:00-06:00');
        $max = Money::fromDecimal('120000');

        $first = $customer->grantIncrease($this->increaseId('01'), Money::fromDecimal('20000'), new IncreaseReason('Compra del almuerzo de la familia'), 'cajero', $at, $max);
        $customer->grantIncrease($this->increaseId('02'), Money::fromDecimal('10000'), new IncreaseReason('Medicinas'), 'cajero', $at, $max);

        $this->assertEquals(new DateTimeImmutable('2026-11-12 00:00:00-06:00'), $first->validUntil);
        $this->assertSame(12_000_000, $customer->effectiveLimit($at)->cents);
        $this->assertSame(9_000_000, $customer->creditLimit->amount->cents, 'The base limit never changes.');
        $this->assertSame(3_500_000, $customer->availableCredit($at)->cents);

        $midnight = new DateTimeImmutable('2026-11-12 00:00:00-06:00');
        $this->assertSame(9_000_000, $customer->effectiveLimit($midnight)->cents);
        $this->assertSame(500_000, $customer->availableCredit($midnight)->cents);
    }

    /** An increase that would pass the maximum is refused, saying how much can still be given. */
    public function test_increase_above_the_maximum_is_refused(): void
    {
        $customer = $this->owing('0');
        $at = new DateTimeImmutable('2026-11-11 10:00:00-06:00');
        $customer->grantIncrease($this->increaseId('01'), Money::fromDecimal('25000'), new IncreaseReason('Emergencia'), 'cajero', $at, Money::fromDecimal('120000'));

        try {
            $customer->grantIncrease($this->increaseId('02'), Money::fromDecimal('5000.01'), new IncreaseReason('Otra'), 'cajero', $at, Money::fromDecimal('120000'));
            $this->fail('The increase should be refused.');
        } catch (CreditIncreaseAboveMaximumException $exception) {
            $this->assertSame(['customer' => 'Ana', 'increase' => '₡5 000,01', 'would_be' => '₡120 000,01', 'max' => '₡120 000', 'left' => '₡5 000'], $exception->parameters());
        }
    }

    /** An increase is reverted once, and only while it still counts. */
    public function test_increase_is_reverted_once_and_only_while_it_counts(): void
    {
        $at = new DateTimeImmutable('2026-11-11 10:00:00-06:00');
        $increase = CreditIncrease::grant($this->increaseId('01'), new NegocioId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d02'), new CustomerId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d01'), Money::fromDecimal('1000'), new IncreaseReason('Emergencia'), 'cajero', $at);

        $increase->revert('admin', $at->modify('+1 hour'));
        $this->assertFalse($increase->isActiveAt($at->modify('+2 hours')));

        try {
            $increase->revert('admin', $at->modify('+2 hours'));
            $this->fail('An increase is reverted only once.');
        } catch (CreditIncreaseNotRevertibleException $exception) {
            $this->assertSame('credit.increase_already_reverted', $exception->translationKey());
        }

        $expired = CreditIncrease::grant($this->increaseId('02'), new NegocioId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d02'), new CustomerId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0d01'), Money::fromDecimal('1000'), new IncreaseReason('Emergencia'), 'cajero', $at);
        $this->expectException(CreditIncreaseNotRevertibleException::class);
        $expired->revert('admin', new DateTimeImmutable('2026-11-12 00:00:00-06:00'));
    }

    /** A fixed moment of the afternoon. */
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-11 15:00:00-06:00');
    }

    /** An identifier for an increase. */
    private function increaseId(string $suffix): CreditIncreaseId
    {
        return new CreditIncreaseId('019a0000-7b1e-7c3a-9f1d-2b6a4e8c0e'.$suffix);
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
