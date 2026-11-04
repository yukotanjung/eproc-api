<?php

namespace Tests\Unit;

use App\Models\ProcurementMethodStage;
use App\Services\StageSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class StageScheduleTest extends TestCase
{
    private function stage(int $id, int $sequence, string $dayType, int $days, bool $isPublic = true): ProcurementMethodStage
    {
        return (new ProcurementMethodStage)->forceFill([
            'id'            => $id,
            'sequence'      => $sequence,
            'name'          => "stage-{$sequence}",
            'day_type'      => $dayType,
            'duration_days' => $days,
            'is_public'     => $isPublic,
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

    public function test_working_days_skip_weekends_and_holidays(): void
    {
        $rows = StageSchedule::build(
            [$this->stage(1, 1, 'working_day', 5)],
            CarbonImmutable::parse('2026-01-09'),
            ['2026-01-13']
        );

        // Fri Jan 9 (1), skip Sat/Sun, Mon Jan 12 (2), skip holiday Tue Jan 13, Wed Jan 14 (3), Thu Jan 15 (4), Fri Jan 16 (5)
        $this->assertSame('2026-01-09', $rows[0]['start_date']);
        $this->assertSame('2026-01-16', $rows[0]['end_date']);
        $this->assertSame('2026-01-17', $rows[0]['next_start_date']);
    }

    public function test_stages_chain_and_snap_working_day_start_past_weekend(): void
    {
        $stages = [
            $this->stage(3, 3, 'calendar', 2),
            $this->stage(1, 1, 'calendar', 5),
            $this->stage(2, 2, 'working_day', 3),
        ];

        $rows = StageSchedule::build($stages, CarbonImmutable::parse('2026-01-05'));

        $this->assertSame([1, 2, 3], array_column($rows, 'sequence'));

        // Mon Jan 5 .. Fri Jan 9 (5 calendar days)
        $this->assertSame('2026-01-05', $rows[0]['start_date']);
        $this->assertSame('2026-01-09', $rows[0]['end_date']);
        $this->assertSame('2026-01-10', $rows[0]['next_start_date']);

        // Next working day after Fri Jan 9 is Mon Jan 12; 3 working days = Mon 12, Tue 13, Wed 14
        $this->assertSame('2026-01-12', $rows[1]['start_date']);
        $this->assertSame('2026-01-14', $rows[1]['end_date']);
        $this->assertSame('2026-01-15', $rows[1]['next_start_date']);

        // Calendar stage starts Thu Jan 15 .. Fri Jan 16
        $this->assertSame('2026-01-15', $rows[2]['start_date']);
        $this->assertSame('2026-01-16', $rows[2]['end_date']);
        $this->assertSame('2026-01-17', $rows[2]['next_start_date']);
    }

    public function test_count_actual_days_respects_day_type_and_holidays(): void
    {
        $holidaySet = StageSchedule::normalizeHolidaySet(['2026-01-12']);

        $this->assertSame(
            5,
            StageSchedule::countActualDays(
                CarbonImmutable::parse('2026-01-09'),
                CarbonImmutable::parse('2026-01-13'),
                'calendar',
                $holidaySet
            )
        );

        // Fri Jan 9 (working), Sat 10, Sun 11, Mon 12 (holiday), Tue 13 (working) => 2 working days
        $this->assertSame(
            2,
            StageSchedule::countActualDays(
                CarbonImmutable::parse('2026-01-09'),
                CarbonImmutable::parse('2026-01-13'),
                'working_day',
                $holidaySet
            )
        );
    }

    public function test_empty_stage_list_returns_empty_array(): void
    {
        $this->assertSame([], StageSchedule::build([], CarbonImmutable::parse('2026-01-05')));
    }
}
