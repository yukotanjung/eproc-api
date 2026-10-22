<?php

namespace Tests\Feature;

use App\Models\ProcurementMethodStage;

class ProcurementMethodTest extends ApiTestCase
{
    public function test_can_create_list_show_update_and_delete_a_method(): void
    {
        $create = $this->actingAsApiUser()->postJson('/api/procurement-method', [
            'name'        => 'Tender Uji',
            'description' => 'Metode untuk pengujian',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('result.name', 'Tender Uji')
            ->assertJsonPath('result.is_active', true)
            ->assertJsonPath('result.stages_count', 0);

        $id = $create->json('result.id');

        $this->actingAsApiUser()
            ->getJson('/api/procurement-method?all=true')
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'Tender Uji');

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$id}")
            ->assertStatus(201)
            ->assertJsonPath('result.id', $id)
            ->assertJsonPath('result.stages', []);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$id}", [
                'name'        => 'Tender Uji Revisi',
                'description' => 'Metode untuk pengujian',
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Tender Uji Revisi');

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement-method/{$id}")
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$id}")
            ->assertStatus(403);
    }

    public function test_name_is_required(): void
    {
        $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['description' => 'tanpa nama'])
            ->assertStatus(400);
    }

    public function test_deleting_a_method_soft_deletes_its_stages(): void
    {
        $id = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => 'Tender Hapus'])
            ->json('result.id');

        $stageId = (int) $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$id}/stage", [
                'name'          => 'Pengumuman',
                'duration_days' => 5,
                'day_type'      => 'calendar',
                'page_key'      => 'procurement/stage/announcement',
                'process_key'   => 'stage.announcement',
            ])
            ->json('result.id');

        $this->actingAsApiUser()->deleteJson("/api/procurement-method/{$id}")->assertStatus(201);

        $this->assertSame(0, ProcurementMethodStage::where('procurement_method_id', $id)->count());
        $this->assertNotNull(ProcurementMethodStage::withTrashed()->find($stageId));
    }
}
