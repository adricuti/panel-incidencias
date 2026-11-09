<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Punto de entrada de `php artisan db:seed` / `migrate:fresh --seed`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Datos coherentes, creados a través del servicio de dominio.
            IncidentSeeder::class,
            // Datos con inconsistencias a propósito, para probar incidents:diagnose.
            InconsistentDataSeeder::class,
        ]);
    }
}
