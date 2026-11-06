<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Unit', 'code' => 'unit'],
            ['name' => 'Pcs', 'code' => 'pcs'],
            ['name' => 'Set', 'code' => 'set'],
            ['name' => 'Paket', 'code' => 'pkt'],
            ['name' => 'Lump Sum', 'code' => 'ls'],
            ['name' => 'Lot', 'code' => 'lot'],
            ['name' => 'Meter', 'code' => 'm'],
            ['name' => 'Meter Persegi', 'code' => 'm2'],
            ['name' => 'Meter Kubik', 'code' => 'm3'],
            ['name' => 'Kilogram', 'code' => 'kg'],
            ['name' => 'Liter', 'code' => 'ltr'],
            ['name' => 'Orang', 'code' => 'org'],
            ['name' => 'Hari', 'code' => 'hr'],
            ['name' => 'Bulan', 'code' => 'bln'],
            ['name' => 'Tahun', 'code' => 'thn'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['name' => $unit['name']],
                [
                    'name'      => $unit['name'],
                    'code'      => $unit['code'],
                    'is_active' => true,
                ]
            );
        }
    }
}
