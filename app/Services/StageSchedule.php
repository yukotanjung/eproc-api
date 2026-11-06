<?php

namespace App\Services;

use App\Models\ProcurementMethodStage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StageSchedule
{
    /**
     * @param  iterable<string>  $holidayDates  Y-m-d strings
     * @return array<string, true>
     */
    public static function normalizeHolidaySet(iterable $holidayDates = []): array
    {
        $set = [];
        foreach ($holidayDates as $date) {
            if (is_string($date) && $date !== '') {
                $set[substr($date, 0, 10)] = true;
            }
        }

        return $set;
    }

    /**
     * @param  array<string, true>  $holidaySet
     */
    public static function isWorkingDay(CarbonImmutable $date, array $holidaySet = []): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return !isset($holidaySet[$date->toDateString()]);
    }

    /**
     * Advances day-by-day until landing on a working day (Monday-Friday excluding holidays).
     *
     * @param  array<string, true>  $holidaySet
     */
    public static function nextWorkingDay(CarbonImmutable $date, array $holidaySet = []): CarbonImmutable
    {
        $cursor = $date->startOfDay();
        $safety = 0;

        while (!self::isWorkingDay($cursor, $holidaySet)) {
            $cursor = $cursor->addDay();
            if (++$safety > 3660) {
                break;
            }
        }

        return $cursor;
    }

    /**
     * Computes the inclusive end date for a stage of $durationDays starting at $startDate.
     *
     * @param  array<string, true>  $holidaySet
     */
    public static function endDateForDuration(
        CarbonImmutable $startDate,
        int $durationDays,
        string $dayType,
        array $holidaySet = []
    ): CarbonImmutable {
        $cursor = $startDate->startOfDay();
        $days = max(1, $durationDays);

        if ($dayType !== ProcurementMethodStage::DAY_TYPE_WORKING_DAY) {
            return $cursor->addDays($days - 1);
        }

        $cursor = self::nextWorkingDay($cursor, $holidaySet);
        $counted = 1;
        $safety = 0;

        while ($counted < $days) {
            $cursor = $cursor->addDay();
            if (self::isWorkingDay($cursor, $holidaySet)) {
                $counted++;
            }
            if (++$safety > 7300) {
                break;
            }
        }

        return $cursor;
    }

    /**
     * Counts effective days between $startDate and $endDate (inclusive) according to $dayType.
     *
     * @param  array<string, true>  $holidaySet
     */
    public static function countActualDays(
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        string $dayType,
        array $holidaySet = []
    ): int {
        $start = $startDate->startOfDay();
        $end = $endDate->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        if ($dayType !== ProcurementMethodStage::DAY_TYPE_WORKING_DAY) {
            return (int) $start->diffInDays($end) + 1;
        }

        $count = 0;
        $cursor = $start;
        while ($cursor->lte($end)) {
            if (self::isWorkingDay($cursor, $holidaySet)) {
                $count++;
            }
            $cursor = $cursor->addDay();
        }

        return $count;
    }

    /**
     * Derives each stage's open window from a start date; nothing is persisted.
     *
     * `end_date` is the last day the stage is open, `next_start_date` is the earliest
     * calendar day the following stage may consider opening. Working-day stages skip
     * weekends and provided holiday dates, snapping their start date to a working day.
     *
     * @param  iterable<ProcurementMethodStage>  $stages
     * @param  iterable<string>  $holidayDates
     * @return array<int, array<string, mixed>>
     */
    public static function build(iterable $stages, CarbonImmutable $startDate, iterable $holidayDates = []): array
    {
        $holidaySet = self::normalizeHolidaySet($holidayDates);
        $cursor = $startDate->startOfDay();
        $rows = [];

        $ordered = Collection::make($stages)->sortBy('sequence')->values();

        foreach ($ordered as $stage) {
            $durationDays = (int) $stage->duration_days;
            $isWorkingDay = $stage->day_type === ProcurementMethodStage::DAY_TYPE_WORKING_DAY;

            $stageStart = $isWorkingDay
                ? self::nextWorkingDay($cursor, $holidaySet)
                : $cursor;

            $stageEnd = self::endDateForDuration($stageStart, $durationDays, $stage->day_type, $holidaySet);
            $nextStart = $stageEnd->addDay();

            $rows[] = [
                'stage_id'        => $stage->id,
                'sequence'        => (int) $stage->sequence,
                'name'            => $stage->name,
                'day_type'        => $stage->day_type,
                'duration_days'   => $durationDays,
                'is_public'       => (bool) ($stage->is_public ?? true),
                'start_date'      => $stageStart->toDateString(),
                'end_date'        => $stageEnd->toDateString(),
                'next_start_date' => $nextStart->toDateString(),
            ];

            $cursor = $nextStart;
        }

        return $rows;
    }
}
