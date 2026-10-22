<?php

namespace App\Services;

use App\Models\ProcurementMethodStage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StageSchedule
{
    /**
     * Derives each stage's open window from a start date; nothing is persisted.
     *
     * `end_date` is the last day the stage is open, `next_start_date` is when the
     * following stage opens. Working-day stages skip Sat/Sun only, not holidays.
     *
     * @param  iterable<ProcurementMethodStage>  $stages
     * @return array<int, array<string, mixed>>
     */
    public static function build(iterable $stages, CarbonImmutable $startDate): array
    {
        $cursor = $startDate->startOfDay();
        $rows = [];

        $ordered = Collection::make($stages)->sortBy('sequence')->values();

        foreach ($ordered as $stage) {
            $durationDays = (int) $stage->duration_days;

            $nextStart = $stage->day_type === ProcurementMethodStage::DAY_TYPE_WORKING_DAY
                ? $cursor->addWeekdays($durationDays)
                : $cursor->addDays($durationDays);

            $rows[] = [
                'stage_id'        => $stage->id,
                'sequence'        => (int) $stage->sequence,
                'name'            => $stage->name,
                'day_type'        => $stage->day_type,
                'duration_days'   => $durationDays,
                'start_date'      => $cursor->toDateString(),
                'end_date'        => $nextStart->subDay()->toDateString(),
                'next_start_date' => $nextStart->toDateString(),
            ];

            $cursor = $nextStart;
        }

        return $rows;
    }
}
