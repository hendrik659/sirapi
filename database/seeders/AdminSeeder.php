<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'role_id' => 2,
            'division_id' => null,

            'name' => 'Admin SIRAPI',
            'email' => 'admin@sirapi.com',

            'phone' => null,
            'employee_number' => 'ADM001',
            'position' => 'Administrator',

            'password' => Hash::make('password123'),

            'is_active' => true,
        ]);
    }
}