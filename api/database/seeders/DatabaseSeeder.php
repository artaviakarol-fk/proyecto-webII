<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Seed the demo data; each module adds its own seeder here. */
    public function run(): void
    {
        $this->call([NegociosDemoSeeder::class]);
    }
}
