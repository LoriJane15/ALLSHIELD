<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class DavaoDelSurMunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('shield.jurisdiction.municipalities') as $name) {
            Municipality::firstOrCreate(['name' => $name]);
        }
    }
}
