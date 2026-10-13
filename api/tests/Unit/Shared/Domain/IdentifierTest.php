<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Identifier;

final class IdentifierTest extends TestCase
{
    private const string UUID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11';

    /** A canonical UUID is accepted as-is. */
    public function test_accepts_a_canonical_uuid(): void
    {
        $this->assertSame(self::UUID, $this->identifier(self::UUID)->value);
    }

    /** Malformed values are rejected with a translatable error. */
    public function test_rejects_a_malformed_value(): void
    {
        try {
            $this->identifier('not-a-uuid');
            $this->fail('A malformed identifier was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('shared.invalid_identifier', $exception->translationKey());
        }
    }

    /** Equality requires the same type and the same value. */
    public function test_compares_by_type_and_value(): void
    {
        $identifier = $this->identifier(self::UUID);
        $otherType = new readonly class(self::UUID) extends Identifier {};

        $this->assertTrue($identifier->equals($this->identifier(self::UUID)));
        $this->assertFalse($identifier->equals($otherType));
    }

    /** Build a concrete identifier for the abstract base. */
    private function identifier(string $value): Identifier
    {
        return new TestIdentifier($value);
    }
}

final readonly class TestIdentifier extends Identifier {}
