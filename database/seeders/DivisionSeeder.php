<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Seluruh Pegawai',
            'Hakim',
            'Kesekretariatan',
            'Kepaniteraan',
        ];

        foreach ($names as $name) {
            Division::firstOrCreate(['name' => $name]);
        }
    }
}

