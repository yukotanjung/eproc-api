<?php

namespace Tests\Feature;

class ProcurementMethodScheduleTest extends ApiTestCase
{
    public function test_preview_returns_effective_dates_for_each_stage(): void
    {
        $methodId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => 'Tender Jadwal'])
            ->json('result.id');

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Pengumuman', 'duration_days' => 5, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/announcement', 'process_key' => 'stage.announcement',
        ])->assertStatus(201);

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Evaluasi', 'duration_days' => 3, 'day_type' => 'working_day',
            'page_key' => 'procurement/stage/evaluation', 'process_key' => 'stage.evaluation',
        ])->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/preview-schedule", ['start_date' => '2026-01-05'])
            ->assertStatus(201)
            ->assertJsonPath('result.start_date', '2026-01-05')
            ->assertJsonPath('result.stages.0.start_date', '2026-01-05')
            ->assertJsonPath('result.stages.0.end_date', '2026-01-09')
            ->assertJsonPath('result.stages.0.next_start_date', '2026-01-10')
            ->assertJsonPath('result.stages.1.start_date', '2026-01-10')
            ->assertJsonPath('result.stages.1.end_date', '2026-01-13')
            ->assertJsonPath('result.stages.1.next_start_date', '2026-01-14');
    }

    public function test_preview_ignores_inactive_stages(): void
    {
        $methodId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => 'Tender Nonaktif'])
            ->json('result.id');

        // store() forces is_active = true, so deactivate through update
        $skippedId = (int) $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Dilewati', 'duration_days' => 9, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/skipped', 'process_key' => 'stage.skipped',
        ])->json('result.id');

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage/{$skippedId}", [
            'name' => 'Dilewati', 'duration_days' => 9, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/skipped', 'process_key' => 'stage.skipped',
            'is_active' => false,
        ])->assertStatus(201);

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Diproses', 'duration_days' => 2, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/processed', 'process_key' => 'stage.processed',
        ])->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/preview-schedule", ['start_date' => '2026-01-05'])
            ->assertStatus(201)
            ->assertJsonCount(1, 'result.stages')
            ->assertJsonPath('result.stages.0.name', 'Diproses')
            ->assertJsonPath('result.stages.0.start_date', '2026-01-05');
    }

    public function test_start_date_is_required(): void
    {
        $methodId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => 'Tender Tanpa Tanggal'])
            ->json('result.id');

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/preview-schedule", [])
            ->assertStatus(400);
    }
}
