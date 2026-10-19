<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\Sales;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Sales\Sales\Domain\ValueObjects\BuyerIdNumber;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class BuyerIdNumberTest extends TestCase
{
    /** The hyphens and spaces of an id number are dropped; only the digits are kept. */
    #[DataProvider('validIdNumbers')]
    public function test_id_number_keeps_only_its_digits(string $written, string $digits): void
    {
        $this->assertSame($digits, new BuyerIdNumber($written)->value);
    }

    /**
     * Fictitious id numbers written in the common ways.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function validIdNumbers(): iterable
    {
        yield 'with hyphens' => ['1-1111-1111', '111111111'];
        yield 'with spaces' => [' 2 2222 2222 ', '222222222'];
        yield 'only digits' => ['333333333', '333333333'];
        yield 'twelve digits' => ['123456789012', '123456789012'];
    }

    /** An id number with letters or a wrong number of digits is rejected. */
    #[DataProvider('invalidIdNumbers')]
    public function test_invalid_id_number_is_rejected(string $written): void
    {
        $this->expectException(InvalidValueException::class);

        new BuyerIdNumber($written);
    }

    /**
     * Values that are not an id number.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidIdNumbers(): iterable
    {
        yield 'too short' => ['12345678'];
        yield 'too long' => ['1234567890123'];
        yield 'with letters' => ['1-ABCD-1111'];
        yield 'empty' => [''];
    }
}
