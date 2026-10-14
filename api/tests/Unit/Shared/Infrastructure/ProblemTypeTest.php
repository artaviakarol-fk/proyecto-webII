<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Infrastructure\Http\Problem\ProblemType;

final class ProblemTypeTest extends TestCase
{
    /** Each status resolves to a type that answers with it; a 401 of the framework means no session. */
    public function test_every_status_resolves_to_a_type_with_that_status(): void
    {
        foreach (ProblemType::cases() as $type) {
            $this->assertSame($type->status(), ProblemType::fromStatus($type->status())->status());
        }

        $this->assertSame(ProblemType::Unauthenticated, ProblemType::fromStatus(401));
    }

    /** Statuses outside the catalog fall back by error class. */
    #[DataProvider('uncataloguedStatuses')]
    public function test_uncatalogued_status_falls_back_by_error_class(int $status, ProblemType $expected): void
    {
        $this->assertSame($expected, ProblemType::fromStatus($status));
    }

    /**
     * Statuses without a type of their own and the fallback they map to.
     *
     * @return array<string, array{int, ProblemType}>
     */
    public static function uncataloguedStatuses(): array
    {
        return [
            'client error' => [418, ProblemType::BadRequest],
            'server error' => [502, ProblemType::InternalError],
        ];
    }
}
