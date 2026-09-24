<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the full SHIELD dataset carried over
     * from the legacy system. Runs on `php artisan migrate --seed` or
     * `php artisan db:seed`, so a fresh clone has every record.
     */
    public function run(): void
    {
        $this->call(LegacyDataSeeder::class);
    }
}
