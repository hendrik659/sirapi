<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Pimpinan',
                'slug' => 'pimpinan',
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin_surat',
            ],
            [
                'name' => 'Ketua Divisi',
                'slug' => 'ketua_divisi',
            ],
            [
                'name' => 'Anggota Divisi',
                'slug' => 'anggota_divisi',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']]
            );
        }
    }
}
