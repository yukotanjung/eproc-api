<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'BUMD',
            'PT',
            'Perseroan',
            'Perorangan',
            'Kemitraan',
            'Koperasi',
            'Firma',
            'CV',
        ];

        foreach ($types as $name) {
            BusinessType::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}
