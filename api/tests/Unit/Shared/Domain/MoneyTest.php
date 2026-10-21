<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Money;

final class MoneyTest extends TestCase
{
    /** Decimal amounts are read into exact céntimos and written back with two decimals. */
    #[DataProvider('amounts')]
    public function test_decimal_amounts_round_trip(string $decimal, int $cents, string $written): void
    {
        $money = Money::fromDecimal($decimal);

        $this->assertSame($cents, $money->cents);
        $this->assertSame($written, $money->toDecimal());
    }

    /**
     * Decimal amounts, their céntimos and how they are written back.
     *
     * @return array<string, array{string, int, string}>
     */
    public static function amounts(): array
    {
        return [
            'entero' => ['1500', 150000, '1500.00'],
            'un decimal' => ['1500.5', 150050, '1500.50'],
            'dos decimales' => ['0.05', 5, '0.05'],
            'cero' => ['0', 0, '0.00'],
        ];
    }

    /** Negative, malformed or too precise amounts are rejected. */
    #[DataProvider('invalidAmounts')]
    public function test_invalid_amount_is_rejected(string $amount): void
    {
        $this->expectException(InvalidValueException::class);

        Money::fromDecimal($amount);
    }

    /**
     * Amounts that are not valid.
     *
     * @return array<string, array{string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'negativo' => ['-1'],
            'tres decimales' => ['1.005'],
            'texto' => ['mil'],
            'demasiado grande' => ['100000000000'],
        ];
    }
}
