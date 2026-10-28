<?php

namespace Tests\Unit;

use App\Models\ProcurementMethodStage;
use App\Services\StageSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class StageScheduleTest extends TestCase
{
    private function stage(int $id, int $sequence, string $dayType, int $days): ProcurementMethodStage
    {
        return (new ProcurementMethodStage)->forceFill([
            'id'            => $id,
            'sequence'      => $sequence,
            'name'          => "stage-{$sequence}",
            'day_type'      => $dayType,
            'duration_days' => $days,
        ]);
    }

    public function test_calendar_days_add_literal_days(): void
    {
        $rows = StageSchedule::build(
            [$this->stage(1, 1, 'calendar', 5)],
            CarbonImmutable::parse('2026-01-05')
        );

        $this->assertSame('2026-01-05', $rows[0]['start_date']);
        $this->assertSame('2026-01-09', $rows[0]['end_date']);
        $this->assertSame('2026-01-10', $rows[0]['next_start_date']);
    }

    public function test_working_days_skip_weekends(): void
    {
        $rows = StageSchedule::build(
            [$this->stage(1, 1, 'working_day', 5)],
            CarbonImmutable::parse('2026-01-09')
        );

        $this->assertSame('2026-01-09', $rows[0]['start_date']);
        $this->assertSame('2026-01-15', $rows[0]['end_date']);
        $this->assertSame('2026-01-16', $rows[0]['next_start_date']);
    }

    public function test_stages_chain_and_mix_day_types(): void
    {
        $stages = [
            $this->stage(3, 3, 'calendar', 2),
            $this->stage(1, 1, 'calendar', 5),
            $this->stage(2, 2, 'working_day', 3),
        ];

        $rows = StageSchedule::build($stages, CarbonImmutable::parse('2026-01-05'));

        $this->assertSame([1, 2, 3], array_column($rows, 'sequence'));

        $this->assertSame('2026-01-05', $rows[0]['start_date']);
        $this->assertSame('2026-01-09', $rows[0]['end_date']);
        $this->assertSame('2026-01-10', $rows[0]['next_start_date']);

        $this->assertSame('2026-01-10', $rows[1]['start_date']);
        $this->assertSame('2026-01-13', $rows[1]['end_date']);
        $this->assertSame('2026-01-14', $rows[1]['next_start_date']);

        $this->assertSame('2026-01-14', $rows[2]['start_date']);
        $this->assertSame('2026-01-15', $rows[2]['end_date']);
        $this->assertSame('2026-01-16', $rows[2]['next_start_date']);
    }

    public function test_empty_stage_list_returns_empty_array(): void
    {
        $this->assertSame([], StageSchedule::build([], CarbonImmutable::parse('2026-01-05')));
    }
}
