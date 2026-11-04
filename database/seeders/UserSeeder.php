<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'BHC Administrator', 'email' => 'admin@bhc.gov.ph', 'role' => 'super_admin'],
            ['name' => 'Nurse Maria', 'email' => 'nurse.maria@bhc.gov.ph', 'role' => 'health_worker'],
        ] as $user) {
            User::updateOrCreate(['email' => $user['email']], [...$user, 'password' => Hash::make('password')]);
        }
    }
}
