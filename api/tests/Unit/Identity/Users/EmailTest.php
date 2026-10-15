<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class EmailTest extends TestCase
{
    /** The address is stored lowercase and without surrounding spaces. */
    public function test_address_is_normalised(): void
    {
        $this->assertSame('ana@minisuper.test', new Email('  Ana@MiniSuper.TEST ')->value);
    }

    /** Malformed or too long addresses are rejected. */
    #[DataProvider('invalidAddresses')]
    public function test_invalid_address_is_rejected(string $address): void
    {
        $this->expectException(InvalidValueException::class);

        new Email($address);
    }

    /**
     * Addresses that are not valid.
     *
     * @return array<string, array{string}>
     */
    public static function invalidAddresses(): array
    {
        return [
            'vacío' => [''],
            'sin arroba' => ['ana.minisuper.test'],
            'demasiado largo' => [str_repeat('a', 250).'@x.test'],
        ];
    }
}
