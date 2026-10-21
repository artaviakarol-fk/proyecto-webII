<?php

declare(strict_types=1);

namespace Tests\Unit\Negocios\OpeningHours\Application;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Negocios\OpeningHours\Application\UseCases\GetOpeningHours;
use Src\Negocios\OpeningHours\Application\UseCases\ReplaceOpeningHours;
use Src\Negocios\OpeningHours\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Tests\Support\Negocios\InMemoryOpeningHoursRepository;
use Tests\Support\Shared\FixedNegocioContext;

final class OpeningHoursUseCasesTest extends TestCase
{
    private const string NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_NEGOCIO_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33';

    private InMemoryOpeningHoursRepository $hours;

    /** Wire the use cases to an in-memory repository. */
    protected function setUp(): void
    {
        $this->hours = new InMemoryOpeningHoursRepository;
    }

    /** A negocio that never set its hours is closed every day. */
    public function test_a_negocio_without_hours_is_closed(): void
    {
        $report = $this->get(self::NEGOCIO_ID)->execute(new DateTimeImmutable('2026-10-20 15:00:00 UTC'));

        $this->assertFalse($report->openNow);
        $this->assertSame([], $report->schedule->slotsOn(Weekday::Tuesday));
    }

    /** Replacing the hours stores them for the negocio and answers whether it is open now, in Costa Rica time. */
    public function test_replacing_stores_the_hours_of_the_negocio(): void
    {
        $replace = new ReplaceOpeningHours(new FixedNegocioContext(new NegocioId(self::NEGOCIO_ID)), $this->hours);

        // 15:00 UTC on Tuesday is 09:00 in Costa Rica.
        $report = $replace->execute(['martes' => [['07:00', '20:00']]], new DateTimeImmutable('2026-10-20 15:00:00 UTC'));

        $this->assertTrue($report->openNow);
        $this->assertCount(1, $this->get(self::NEGOCIO_ID)->execute(new DateTimeImmutable)->schedule->slotsOn(Weekday::Tuesday));
        $this->assertSame([], $this->get(self::OTHER_NEGOCIO_ID)->execute(new DateTimeImmutable)->schedule->slotsOn(Weekday::Tuesday));
    }

    /** Overlapping slots are rejected and the stored hours stay as they were. */
    public function test_invalid_hours_change_nothing(): void
    {
        $replace = new ReplaceOpeningHours(new FixedNegocioContext(new NegocioId(self::NEGOCIO_ID)), $this->hours);
        $replace->execute(['lunes' => [['08:00', '12:00']]], new DateTimeImmutable);

        try {
            $replace->execute(['lunes' => [['08:00', '12:00'], ['11:00', '14:00']]], new DateTimeImmutable);
            $this->fail('Overlapping slots were accepted.');
        } catch (InvalidValueException) {
            $this->assertCount(1, $this->get(self::NEGOCIO_ID)->execute(new DateTimeImmutable)->schedule->slotsOn(Weekday::Monday));
        }
    }

    /** The query use case for one negocio. */
    private function get(string $negocioId): GetOpeningHours
    {
        return new GetOpeningHours(new FixedNegocioContext(new NegocioId($negocioId)), $this->hours);
    }
}
