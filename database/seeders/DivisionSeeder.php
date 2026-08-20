<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            'PEM' => 'Pemasaran',
            'KEU' => 'Keuangan',
            'IKL' => 'Iklan',
            'RED' => 'Redaksi',
            'OFF' => 'Offprint',
            'PCT' => 'Pracetak',
            'SDM' => 'SDM & Umum',
        ];

        foreach ($divisions as $code => $name) {
            $division = Division::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_active' => true,
                ],
            );

            if ($division->name !== $name) {
                $division->update(['name' => $name]);
            }
        }
    }
}
