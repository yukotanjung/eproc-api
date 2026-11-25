<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProvinceCitySeeder extends Seeder
{
    public function run(): void
    {
        $provinceResponse = Http::timeout(30)->get('https://emsifa.github.io/api-wilayah-indonesia/api/provinces.json');

        if ($provinceResponse->failed()) {
            throw new RuntimeException('Failed to fetch provinces from API.');
        }

        $provinces = $provinceResponse->json() ?? [];

        foreach ($provinces as $provinceData) {
            $provinceName = ucwords(strtolower($provinceData['name']));
            $province = Province::query()->updateOrCreate(
                ['name' => $provinceName],
                ['name' => $provinceName, 'is_active' => true]
            );

            $cityResponse = Http::timeout(30)->get("https://emsifa.github.io/api-wilayah-indonesia/api/regencies/{$provinceData['id']}.json");

            if ($cityResponse->failed()) {
                continue;
            }

            $cities = $cityResponse->json() ?? [];

            foreach ($cities as $cityData) {
                $cityName = ucwords(strtolower($cityData['name']));
                City::query()->updateOrCreate(
                    [
                        'province_id' => $province->id,
                        'name'        => $cityName,
                    ],
                    [
                        'name'      => $cityName,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
