<?php

namespace Database\Seeders;

use App\Models\GovAgency;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalUserAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = Municipality::firstOrCreate(['name' => 'Digos City']);
        $agency = GovAgency::firstOrCreate(
            ['acronym' => 'DOH'],
            ['name' => 'Department of Health'],
        );

        $password = 'Shield-Local-2026-Test!';
        $accounts = [
            ['super_admin', 'System Super Administrator', 'super_admin'],
            ['katuparan_admin', 'System Katuparan Administrator', 'admin'],
            ['lgu_officer', 'System LGU Officer', 'lgu'],
            ['agency_officer', 'System Government Agency Officer', 'gov_agency'],
            ['mblrc_officer', 'System MBLRC Officer', 'mblrc'],
            ['ib39_officer', 'System 39th IB Officer', '39th_ib'],
            ['afp_officer', 'System AFP Officer', 'afp'],
            ['japic_officer', 'System JAPIC Officer', 'japic'],
            ['pswdo_officer', 'System PSWDO Officer', 'pswdo'],
        ];

        foreach ($accounts as [$username, $name, $role]) {
            User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => null,
                    'password' => $password,
                    'role' => $role,
                    'is_active' => true,
                    'municipality_id' => $role === 'lgu' ? $municipality->id : null,
                    'gov_agency_id' => $role === 'gov_agency' ? $agency->id : null,
                ],
            );
        }
    }
}
