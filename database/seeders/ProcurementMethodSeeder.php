<?php

namespace Database\Seeders;

use App\Models\ProcurementMethod;
use App\Models\ProcurementMethodStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProcurementMethodSeeder extends Seeder
{
    public function run(): void
    {
        $method = ProcurementMethod::updateOrCreate(
            ['name' => 'Tender Umum Pascakualifikasi Metode Kualitas dan Biaya 2 File'],
            [
                'name'        => 'Tender Umum Pascakualifikasi Metode Kualitas dan Biaya 2 File',
                'description' => 'Tender Umum Pascakualifikasi Metode Kualitas dan Biaya 2 File',
                'is_active'   => true,
            ]
        );

        $stages = [
            [
                'sequence'      => 1,
                'name'          => 'Pengumuman',
                'duration_days' => 7,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_CALENDAR,
            ],
            [
                'sequence'      => 2,
                'name'          => 'Pendaftaran',
                'duration_days' => 7,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_CALENDAR,
            ],
            [
                'sequence'      => 3,
                'name'          => 'Pemberian Penjelasan (Aanwijzing)',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 4,
                'name'          => 'Upload Berita Acara Pemberian Penjelasan Pekerjaan (Aanwijzing)',
                'duration_days' => 2,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_CALENDAR,
            ],
            [
                'sequence'      => 5,
                'name'          => 'Pemasukan Penawaran File I',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 6,
                'name'          => 'Pemasukan Penawaran File II',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 7,
                'name'          => 'Pembukaan Penawaran File I',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 8,
                'name'          => 'Hasil Evaluasi Penawaran File I',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 9,
                'name'          => 'Pembukaan Penawaran File II',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 10,
                'name'          => 'Hasil Evaluasi Penawaran File II',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 11,
                'name'          => 'Penetapan Pemenang',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 12,
                'name'          => 'Pengumuman Pemenang',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 13,
                'name'          => 'Masa Sanggah',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
            [
                'sequence'      => 14,
                'name'          => 'Jawaban Sanggah',
                'duration_days' => 1,
                'day_type'      => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            ],
        ];

        $method->stages()->forceDelete();

        foreach ($stages as $stage) {
            $slug = Str::limit(Str::slug($stage['name']), 70, '');
            ProcurementMethodStage::create([
                'procurement_method_id' => $method->id,
                'sequence'              => $stage['sequence'],
                'name'                  => $stage['name'],
                'duration_days'         => $stage['duration_days'],
                'day_type'              => $stage['day_type'],
                'requires_file'         => false,
                'page_key'              => "procurement/stage/{$slug}",
                'process_key'           => "stage.{$slug}",
                'description'           => null,
                'is_active'             => true,
            ]);
        }
    }
}
