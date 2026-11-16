<?php

declare(strict_types=1);

namespace Tests\Unit\Sales\SaleDrafts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Sales\SaleDrafts\Domain\Services\NameMatcher;

final class NameMatcherTest extends TestCase
{
    /** @var array<string, string> */
    private const array CATALOG = [
        'Leche entera 1 L' => 'leche-entera',
        'Leche descremada 1 L' => 'leche-descremada',
        'Pan cuadrado' => 'pan',
        'Arroz 1 kg' => 'arroz',
        'Frijoles negros 900 g' => 'frijoles',
    ];

    /** Names written the way a cashier writes them find the product of the catalog. */
    #[DataProvider('written')]
    public function test_finds_the_product_ignoring_case_accents_and_plurals(string $written, ?string $expected, bool $ambiguous): void
    {
        $match = NameMatcher::best($written, self::CATALOG);

        $this->assertSame($expected, $match[0] ?? null);
        $this->assertSame($ambiguous, $match[1] ?? false);
    }

    /**
     * What the cashier writes, the product it means and whether another one is just as close.
     *
     * @return array<string, array{string, ?string, bool}>
     */
    public static function written(): array
    {
        return [
            'nombre completo' => ['Leche entera 1 L', 'leche-entera', false],
            'parte del nombre' => ['leche entera', 'leche-entera', false],
            'plural con -es' => ['panes', 'pan', false],
            'plural con -ces' => ['arroces', 'arroz', false],
            'plural sencillo' => ['frijoles', 'frijoles', false],
            'mayúsculas' => ['PAN', 'pan', false],
            'no existe' => ['café', null, false],
        ];
    }

    /** "2 leches" matches two products equally: one is proposed and the draft is marked as ambiguous. */
    public function test_two_products_just_as_close_are_ambiguous(): void
    {
        $match = NameMatcher::best('2 leches', self::CATALOG);

        $this->assertNotNull($match);
        $this->assertContains($match[0], ['leche-entera', 'leche-descremada']);
        $this->assertTrue($match[1]);
    }

    /** Customers are found with or without accents and courtesy words, and a different name is not forced. */
    public function test_finds_the_customer(): void
    {
        $customers = ['Doña María Rodríguez' => 'maria', 'Don José Alfaro' => 'jose'];

        $this->assertSame('maria', NameMatcher::best('doña maria', $customers)[0] ?? null);
        $this->assertSame('jose', NameMatcher::best('Jose Alfaro', $customers)[0] ?? null);
        $this->assertNull(NameMatcher::best('Josefa', $customers));
        $this->assertNull(NameMatcher::best('   ', $customers));
    }
}
