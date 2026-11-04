<?php

namespace Tests\Feature;

use App\Models\Holiday;

class HolidayTest extends ApiTestCase
{
    public function test_can_create_list_filter_update_and_delete_holiday(): void
    {
        $create = $this->actingAsApiUser()->postJson('/api/holiday', [
            'holiday_date' => '2027-08-17',
            'name'         => 'Hari Kemerdekaan RI',
            'description'  => 'Libur Nasional',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('result.holiday_date', '2027-08-17')
            ->assertJsonPath('result.name', 'Hari Kemerdekaan RI')
            ->assertJsonPath('result.is_active', true);

        $id = (int) $create->json('result.id');

        // Duplicate active holiday date is rejected
        $this->actingAsApiUser()->postJson('/api/holiday', [
            'holiday_date' => '2027-08-17',
            'name'         => 'Duplikat Tanggal',
        ])->assertStatus(400);

        $this->actingAsApiUser()
            ->getJson('/api/holiday?year=2027&all=true')
            ->assertStatus(201)
            ->assertJsonFragment(['id' => $id, 'holiday_date' => '2027-08-17']);

        $this->actingAsApiUser()
            ->getJson("/api/holiday/{$id}")
            ->assertStatus(201)
            ->assertJsonPath('result.id', $id);

        $this->actingAsApiUser()
            ->postJson("/api/holiday/{$id}", [
                'holiday_date' => '2027-08-18',
                'name'         => 'Cuti Bersama Kemerdekaan',
                'description'  => 'Revisi tanggal',
                'is_active'    => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.holiday_date', '2027-08-18')
            ->assertJsonPath('result.is_active', false);

        $this->actingAsApiUser()
            ->deleteJson("/api/holiday/{$id}")
            ->assertStatus(201);

        $this->assertNotNull(Holiday::withTrashed()->find($id));
    }
}
