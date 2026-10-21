<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Negocios\OpeningHours\Domain\Contracts\OpeningHoursRepository;
use Src\Negocios\OpeningHours\Domain\ValueObjects\WeeklySchedule;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

final class HorariosDemoSeeder extends Seeder
{
    /**
     * Opening hours of the Minisúper Teresita, as published by the negocio.
     *
     * @var array<string, list<array{string, string}>>
     */
    private const array TERESITA = [
        'lunes' => [['07:00', '20:00']],
        'martes' => [['07:00', '20:00']],
        'miercoles' => [['07:00', '20:00']],
        'jueves' => [['07:00', '20:00']],
        'viernes' => [['07:00', '20:00']],
        'sabado' => [['07:00', '18:00']],
        'domingo' => [['07:00', '18:00']],
    ];

    /**
     * Fictitious opening hours of the Minisúper La Esquina: it stays closed on Sundays.
     *
     * @var array<string, list<array{string, string}>>
     */
    private const array LA_ESQUINA = [
        'lunes' => [['06:30', '21:00']],
        'martes' => [['06:30', '21:00']],
        'miercoles' => [['06:30', '21:00']],
        'jueves' => [['06:30', '21:00']],
        'viernes' => [['06:30', '21:00']],
        'sabado' => [['06:30', '21:00']],
        'domingo' => [],
    ];

    /** Seed the opening hours of each demo negocio under its own row-level security scope. */
    public function run(NegocioTransaction $transaction, OpeningHoursRepository $hours): void
    {
        $schedules = [
            NegociosDemoSeeder::TERESITA_ID => self::TERESITA,
            NegociosDemoSeeder::LA_ESQUINA_ID => self::LA_ESQUINA,
        ];

        foreach ($schedules as $negocioId => $days) {
            $transaction->run($negocioId, function () use ($hours, $negocioId, $days): void {
                $hours->replace(new NegocioId($negocioId), WeeklySchedule::fromTimes($days));
            });
        }
    }
}
