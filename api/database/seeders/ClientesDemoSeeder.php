<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Credit\Customers\Infrastructure\Persistence\Models\CustomerModel;
use Src\Shared\Infrastructure\Tenancy\NegocioTransaction;

final class ClientesDemoSeeder extends Seeder
{
    /**
     * Fictitious customers of fiado: name, phone, base limit, active. None of them is a real person,
     * and all of them start owing nothing.
     *
     * @var list<array{string, string|null, string, bool}>
     */
    private const array CUSTOMERS = [
        ['Doña María Rodríguez', '8888-0001', '90000.00', true],
        ['Don José Alfaro', '8888-0002', '60000.00', true],
        ['Ana Lucía Quesada', null, '30000.00', true],
        ['Luis Vargas', '8888-0004', '90000.00', false],
    ];

    /** Seed the same customers in each demo negocio, each one under its own row-level security scope. */
    public function run(NegocioTransaction $transaction): void
    {
        foreach ([NegociosDemoSeeder::TERESITA_ID, NegociosDemoSeeder::LA_ESQUINA_ID] as $negocioId) {
            $transaction->run($negocioId, function () use ($negocioId): void {
                foreach (self::CUSTOMERS as [$name, $phone, $limit, $active]) {
                    CustomerModel::query()->updateOrCreate(
                        ['negocio_id' => $negocioId, 'nombre' => $name],
                        ['telefono' => $phone, 'limite_fiado' => $limit, 'activo' => $active],
                    );
                }
            });
        }
    }
}
