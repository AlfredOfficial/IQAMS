<?php

namespace Database\Seeders;

use App\Models\OfficeUnit;
use Illuminate\Database\Seeder;

class OfficeUnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'REG' => 'Registrar', 'ACC' => 'Accounting', 'HR' => 'Human Resources',
            'LIB' => 'Library', 'MIS' => 'MIS/IT', 'SAO' => 'Student Affairs',
        ] as $code => $name) {
            OfficeUnit::firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
