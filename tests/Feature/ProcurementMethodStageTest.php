<?php

namespace Tests\Feature;

class ProcurementMethodStageTest extends ApiTestCase
{
    private function createMethod(string $name = 'Tender Uji'): int
    {
        return (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => $name])
            ->json('result.id');
    }

    private function stagePayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Pengumuman',
            'duration_days' => 5,
            'day_type'      => 'calendar',
            'requires_file' => false,
            'page_key'      => 'procurement/stage/announcement',
            'process_key'   => 'stage.announcement',
            'description'   => null,
        ], $overrides);
    }

    public function test_stage_is_appended_with_incrementing_sequence(): void
    {
        $methodId = $this->createMethod();

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload())
            ->assertStatus(201)
            ->assertJsonPath('result.sequence', 1)
            ->assertJsonPath('result.requires_file', false);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload([
                'name'          => 'Evaluasi',
                'day_type'      => 'working_day',
                'duration_days' => 3,
                'requires_file' => true,
                'page_key'      => 'procurement/stage/evaluation',
                'process_key'   => 'stage.evaluation',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('result.sequence', 2)
            ->assertJsonPath('result.day_type', 'working_day')
            ->assertJsonPath('result.requires_file', true);

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$methodId}/stage")
            ->assertStatus(201)
            ->assertJsonPath('result.0.sequence', 1)
            ->assertJsonPath('result.1.sequence', 2);

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$methodId}")
            ->assertStatus(201)
            ->assertJsonPath('result.stages_count', 2)
            ->assertJsonPath('result.stages.1.name', 'Evaluasi');
    }

    public function test_day_type_must_be_calendar_or_working_day(): void
    {
        $methodId = $this->createMethod();

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['day_type' => 'bulan']))
            ->assertStatus(400);
    }

    public function test_duration_days_must_be_at_least_one(): void
    {
        $methodId = $this->createMethod();

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['duration_days' => 0]))
            ->assertStatus(400);
    }

    public function test_stage_on_unknown_method_returns_403(): void
    {
        $this->actingAsApiUser()
            ->postJson('/api/procurement-method/999999/stage', $this->stagePayload())
            ->assertStatus(403);
    }

    public function test_stage_update_keeps_sequence(): void
    {
        $methodId = $this->createMethod();
        $stageId = (int) $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload())
            ->json('result.id');

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/{$stageId}", $this->stagePayload([
                'name'          => 'Pengumuman Revisi',
                'duration_days' => 7,
                'requires_file' => true,
                'is_active'     => false,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Pengumuman Revisi')
            ->assertJsonPath('result.duration_days', 7)
            ->assertJsonPath('result.requires_file', true)
            ->assertJsonPath('result.is_active', false)
            ->assertJsonPath('result.sequence', 1);
    }

    public function test_delete_compacts_sequence(): void
    {
        $methodId = $this->createMethod();

        $ids = [];
        foreach (['A', 'B', 'C'] as $name) {
            $ids[$name] = (int) $this->actingAsApiUser()
                ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => $name]))
                ->json('result.id');
        }

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement-method/{$methodId}/stage/{$ids['B']}")
            ->assertStatus(201);

        $remaining = $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$methodId}/stage")
            ->assertStatus(201);

        $this->assertSame(['A', 'C'], array_column($remaining->json('result'), 'name'));
        $this->assertSame([1, 2], array_column($remaining->json('result'), 'sequence'));
    }

    public function test_stage_of_another_method_is_not_reachable(): void
    {
        $first = $this->createMethod('Metode Satu');
        $second = $this->createMethod('Metode Dua');

        $stageId = (int) $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$first}/stage", $this->stagePayload())
            ->json('result.id');

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement-method/{$second}/stage/{$stageId}")
            ->assertStatus(403);
    }

    public function test_reorder_rewrites_sequence_contiguously(): void
    {
        $methodId = $this->createMethod();

        $ids = [];
        foreach (['A', 'B', 'C'] as $name) {
            $ids[$name] = (int) $this->actingAsApiUser()
                ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => $name]))
                ->json('result.id');
        }

        $response = $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/reorder", [
                'stage_ids' => [$ids['C'], $ids['A'], $ids['B']],
            ])
            ->assertStatus(201);

        $this->assertSame(['C', 'A', 'B'], array_column($response->json('result'), 'name'));
        $this->assertSame([1, 2, 3], array_column($response->json('result'), 'sequence'));

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/reorder", [
                'stage_ids' => [$ids['A'], $ids['C'], $ids['B']],
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'A')
            ->assertJsonPath('result.1.name', 'C');
    }

    public function test_reorder_rejects_incomplete_id_list(): void
    {
        $methodId = $this->createMethod();

        $first = (int) $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => 'A']))
            ->json('result.id');

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => 'B']))
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/reorder", ['stage_ids' => [$first]])
            ->assertStatus(400);
    }
}
