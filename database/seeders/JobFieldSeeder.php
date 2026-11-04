<?php

namespace Database\Seeders;

use App\Models\JobField;
use App\Models\JobSubField;
use App\Models\JobType;
use Illuminate\Database\Seeder;

class JobFieldSeeder extends Seeder
{
    public function run(): void
    {
        $jobType = JobType::find(4) ?? JobType::where('name', 'Pekerjaan Konstruksi')->first();
        $jobTypeId = $jobType ? $jobType->id : 4;

        $jobFields = [
            'Jasa Pelaksanaan Spesialis',
            'Instalasi Mekanikal dan Elektrikal',
            'Bangunan Sipil',
            'Jasa Pelaksana Ketrampilan',
            'Bangunan Gedung',
            'Jasa Pelaksanaan Lainnya',
        ];

        foreach ($jobFields as $name) {
            JobField::query()->updateOrCreate(
                [
                    'job_type_id' => $jobTypeId,
                    'name'        => $name,
                ],
                [
                    'is_active' => true,
                ]
            );
        }

        $parentField = JobField::query()
            ->where('job_type_id', $jobTypeId)
            ->where('name', 'Jasa Pelaksanaan Lainnya')
            ->first();

        if ($parentField) {
            $subFields = [
                'PL001 (Jasa Penyewaan Alat Konstruksi dan Pembongkaran Bangunan atau Pekerjaan Sipil Lainnya dengan Operator)',
                'PL002 (Jasa Pelaksana Perakitan dan Pemasangan Konstruksi Prafabrikasi untuk Konstruksi Bangunan Gedung)',
                'PL003 (Jasa Pelaksana Perakitan dan Pemasangan Konstruksi Prafabrikasi untuk Konstruksi Jalan dan Jembatan Serta Rel Kereta Api)',
                '46639 (Perdagangan Besar Bahan Konstruksi Lainnya)',
                '24103 (IndustriPipa dan Sambungan Pipa dari Baja dan Besi)',
                'PL002 (Pengerukan)',
                '24205 (Industri Pipa dan Sambungan Pipa Dari Logam Bukan besi dan Baja)',
                '30111 (Industri Kapal dan Perahu)',
                '30112 (Industri Bangunan Lepas Pantai dan Bangunan Terapung)',
                '30113 (Industri Peralatan, Perlengkapan dan Bagian Kapal)',
                '46599 (Perdagangan Besar Mesin, Peralatan dan Perlengkapan Lainnya)',
                'PL004 (Jasa Pelaksana Perakitan dan Pemasangan Konstruksi Prafabrikasi untuk Konstruksi Prasarana Sumber Daya Air, Irigasi, Dermaga, Pelabuhan, Persungaian, Pantai serta Bangunan Pengolahan Air bersih, limbah dan sampah (insinerator))',
            ];

            foreach ($subFields as $name) {
                JobSubField::query()->updateOrCreate(
                    [
                        'job_field_id' => $parentField->id,
                        'name'         => $name,
                    ],
                    [
                        'job_type_id' => $jobTypeId,
                        'is_active'   => true,
                    ]
                );
            }
        }
    }
}
