<?php

namespace Database\Seeders;

use App\Models\JobType;
use Illuminate\Database\Seeder;

class JobTypeSeeder extends Seeder
{
    public function run(): void
    {
        $jobTypes = [
            'Pengadaan Barang',
            'Pekerjaan Terintegrasi',
            'Jasa Lainnya',
            'Pekerjaan Konstruksi',
            'Jasa Konsultansi',
        ];

        foreach ($jobTypes as $name) {
            JobType::query()->updateOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );
        }
    }
}
