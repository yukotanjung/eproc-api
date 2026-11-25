<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            'SD',
            'SMP',
            'SMA/SMK',
            'D1',
            'D2',
            'D3',
            'D4/S1',
            'S2',
            'S3',
        ];

        foreach ($levels as $name) {
            EducationLevel::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}
