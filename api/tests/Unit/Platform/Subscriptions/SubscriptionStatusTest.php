<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Subscriptions;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Platform\Subscriptions\Domain\Entities\Subscription;
use Src\Platform\Subscriptions\Domain\ValueObjects\ServiceStatus;
use Src\Platform\Subscriptions\Domain\ValueObjects\SubscriptionTerms;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

final class SubscriptionStatusTest extends TestCase
{
    private const string NEGOCIO_ID = '019a0000-0000-7000-8000-0000000000aa';

    /**
     * Days from the moment it expires to now, and the state the negocio is in.
     *
     * @return array<string, array{string, ServiceStatus}>
     */
    public static function moments(): array
    {
        return [
            'faltan 8 días' => ['-8 days', ServiceStatus::Activo],
            'faltan 7 días' => ['-7 days', ServiceStatus::PorVencer],
            'falta 1 hora' => ['-1 hour', ServiceStatus::PorVencer],
            'venció hace 1 hora' => ['+1 hour', ServiceStatus::EnGracia],
            'venció hace 2 días y 23 horas' => ['+2 days 23 hours', ServiceStatus::EnGracia],
            'venció hace 3 días' => ['+3 days', ServiceStatus::Suspendido],
        ];
    }

    /** Yellow 7 days before, red for 3 days after, then suspended. */
    #[DataProvider('moments')]
    public function test_the_state_follows_the_date(string $fromExpiry, ServiceStatus $expected): void
    {
        $expiresAt = new DateTimeImmutable('2026-12-01 10:00:00');

        $this->assertSame($expected, $this->subscription($expiresAt)->status($expiresAt->modify($fromExpiry), self::terms()));
    }

    /** A negocio turned off by the platform is suspended even with the rent paid. */
    public function test_a_negocio_turned_off_is_suspended(): void
    {
        $now = new DateTimeImmutable('2026-11-19 10:00:00');

        $this->assertSame(ServiceStatus::Suspendido, $this->subscription($now->modify('+20 days'))->status($now, self::terms(), false));
    }

    /** The free trial lasts the days of the terms and counts as trial. */
    public function test_the_trial_lasts_five_days(): void
    {
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $trial = Subscription::startTrial(new NegocioId(self::NEGOCIO_ID), $now, self::terms());

        $this->assertTrue($trial->trial);
        $this->assertSame(5, $trial->daysLeft($now));
        $this->assertSame(ServiceStatus::PorVencer, $trial->status($now, self::terms()));
    }

    /** Paying before the date adds the months after it; paying after the date counts from the payment. */
    public function test_paying_adds_months_from_the_later_of_the_date_and_the_payment(): void
    {
        $expiresAt = new DateTimeImmutable('2026-12-01 10:00:00');

        $early = $this->subscription($expiresAt)->extend(2, new DateTimeImmutable('2026-11-25 10:00:00'));
        $late = $this->subscription($expiresAt)->extend(1, new DateTimeImmutable('2026-12-03 09:00:00'));

        $this->assertEquals(new DateTimeImmutable('2027-02-01 10:00:00'), $early->expiresAt);
        $this->assertEquals(new DateTimeImmutable('2027-01-03 09:00:00'), $late->expiresAt);
        $this->assertFalse($late->trial);
    }

    private function subscription(DateTimeImmutable $expiresAt): Subscription
    {
        return Subscription::reconstitute(new NegocioId(self::NEGOCIO_ID), $expiresAt, false);
    }

    private static function terms(): SubscriptionTerms
    {
        return new SubscriptionTerms(new Money(1_500_000), 5, 7, 3);
    }
}
