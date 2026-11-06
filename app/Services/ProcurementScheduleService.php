<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\Procurement;
use App\Models\ProcurementMethodStage;
use App\Models\ProcurementSchedule;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcurementScheduleService
{
    public const DEFAULT_START_TIME = '08:00';
    public const DEFAULT_END_TIME = '16:00';

    /**
     * Copies active stages from the procurement's method as independent snapshot rows.
     * Existing schedule rows on the procurement (if any) are soft-deleted first.
     */
    public function syncSnapshotStages(Procurement $procurement): Collection
    {
        $procurement->checklistFiles()->delete();
        $procurement->schedules()->delete();

        $masterStages = ProcurementMethodStage::where('procurement_method_id', $procurement->procurement_method_id)
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();

        $created = collect();

        foreach ($masterStages->values() as $index => $stage) {
            $created->push(ProcurementSchedule::create([
                'procurement_id'              => $procurement->id,
                'procurement_method_stage_id' => $stage->id,
                'sequence'                    => $index + 1,
                'name'                        => $stage->name,
                'duration_days'               => (int) $stage->duration_days,
                'day_type'                    => $stage->day_type,
                'requires_file'               => (bool) $stage->requires_file,
                'is_public'                   => (bool) ($stage->is_public ?? true),
                'page_key'                    => $stage->page_key,
                'process_key'                 => $stage->process_key,
                'description'                 => $stage->description,
                'start_at'                    => null,
                'end_at'                      => null,
            ]));
        }

        return $created;
    }

    /**
     * Returns ordered schedule rows for the procurement, lazily initializing snapshots
     * if the procurement predates schedule tracking and has active method stages.
     *
     * @return Collection<int, ProcurementSchedule>
     */
    public function getOrderedSchedules(Procurement $procurement): Collection
    {
        $schedules = $procurement->schedules()->orderBy('sequence')->get();

        if ($schedules->isEmpty()) {
            $hasMasterStages = ProcurementMethodStage::where('procurement_method_id', $procurement->procurement_method_id)
                ->where('is_active', true)
                ->exists();

            if ($hasMasterStages) {
                return DB::transaction(fn () => $this->syncSnapshotStages($procurement));
            }
        }

        return $schedules;
    }

    /**
     * Formats all schedules of a procurement for internal API responses.
     */
    public function listForProcurement(Procurement $procurement): array
    {
        $holidaySet = StageSchedule::normalizeHolidaySet(Holiday::activeDateStrings());

        return $this->getOrderedSchedules($procurement)
            ->map(fn (ProcurementSchedule $s) => $s->toApiArray($holidaySet))
            ->values()
            ->all();
    }

    /**
     * Formats public-only schedules of an active, non-cancelled procurement.
     */
    public function listPublicForProcurement(Procurement $procurement): array
    {
        $holidaySet = StageSchedule::normalizeHolidaySet(Holiday::activeDateStrings());

        return $this->getOrderedSchedules($procurement)
            ->filter(fn (ProcurementSchedule $s) => (bool) $s->is_public)
            ->map(fn (ProcurementSchedule $s) => $s->toPublicApiArray($holidaySet))
            ->values()
            ->all();
    }

    /**
     * Auto-generates conflict-free schedules from $startDate (or procurement planned_date),
     * optionally starting at $fromSequence and optionally persisting to database.
     *
     * @param  array{
     *     start_date?: ?string,
     *     from_sequence?: ?int,
     *     start_time?: ?string,
     *     end_time?: ?string,
     *     save?: bool
     * }  $options
     */
    public function generate(Procurement $procurement, array $options = []): array
    {
        if ($procurement->status === Procurement::STATUS_CANCELLED) {
            throw new DomainException('Cannot generate schedule for cancelled procurement');
        }

        $startTime = $options['start_time'] ?? self::DEFAULT_START_TIME;
        $endTime = $options['end_time'] ?? self::DEFAULT_END_TIME;
        $fromSequence = isset($options['from_sequence']) ? (int) $options['from_sequence'] : 1;
        $save = (bool) ($options['save'] ?? false);
        $requestedStartDate = $options['start_date'] ?? null;

        if ($this->normalizeHms($endTime) <= $this->normalizeHms($startTime)) {
            throw new DomainException('end_time must be after start_time');
        }

        $schedules = $this->getOrderedSchedules($procurement);
        if ($schedules->isEmpty()) {
            return [
                'procurement_id' => (int) $procurement->id,
                'start_date'     => $requestedStartDate ?? ($procurement->planned_date ? $procurement->planned_date->format('Y-m-d') : null),
                'from_sequence'  => $fromSequence,
                'saved'          => $save,
                'schedules'      => [],
            ];
        }

        $maxSequence = (int) $schedules->max('sequence');
        if ($fromSequence < 1 || $fromSequence > $maxSequence) {
            throw new DomainException("from_sequence must be between 1 and {$maxSequence}");
        }

        $lockedStages = $schedules->filter(fn (ProcurementSchedule $s) => (int) $s->sequence < $fromSequence)->values();
        $targetStages = $schedules->filter(fn (ProcurementSchedule $s) => (int) $s->sequence >= $fromSequence)->values();

        if ($lockedStages->isNotEmpty()) {
            foreach ($lockedStages as $locked) {
                if (!$locked->start_at || !$locked->end_at) {
                    throw new DomainException(
                        "Stage #{$locked->sequence} ({$locked->name}) must be scheduled before generating from sequence {$fromSequence}"
                    );
                }
            }

            $lastLockedEnd = CarbonImmutable::instance($lockedStages->last()->end_at);
            $minStartDate = $lastLockedEnd->startOfDay()->addDay();

            if ($requestedStartDate !== null) {
                $cursorDate = CarbonImmutable::parse($requestedStartDate)->startOfDay();
                if ($cursorDate->lt($minStartDate)) {
                    throw new DomainException(
                        "start_date ({$cursorDate->toDateString()}) must be at least {$minStartDate->toDateString()} (H+1 after previous stage)"
                    );
                }
            } else {
                $cursorDate = $minStartDate;
            }
        } else {
            $baseDate = $requestedStartDate
                ?? ($procurement->planned_date ? $procurement->planned_date->format('Y-m-d') : now()->format('Y-m-d'));
            $cursorDate = CarbonImmutable::parse($baseDate)->startOfDay();
        }

        $effectiveStartDate = $cursorDate->toDateString();
        $holidaySet = StageSchedule::normalizeHolidaySet(Holiday::activeDateStrings());
        $otherSchedules = $this->otherActiveSchedules((int) $procurement->id);

        foreach ($targetStages as $stage) {
            [$slotStart, $slotEnd] = $this->findNextAvailableWindow(
                $cursorDate,
                (int) $stage->duration_days,
                $stage->day_type,
                $startTime,
                $endTime,
                $holidaySet,
                $otherSchedules
            );

            $stage->start_at = $slotStart;
            $stage->end_at = $slotEnd;

            $cursorDate = $slotEnd->startOfDay()->addDay();
        }

        if ($save) {
            DB::transaction(function () use ($targetStages) {
                foreach ($targetStages as $stage) {
                    $stage->save();
                }
            });
        }

        return [
            'procurement_id' => (int) $procurement->id,
            'start_date'     => $effectiveStartDate,
            'from_sequence'  => $fromSequence,
            'saved'          => $save,
            'schedules'      => $schedules
                ->map(fn (ProcurementSchedule $s) => $s->toApiArray($holidaySet))
                ->values()
                ->all(),
        ];
    }

    /**
     * Validates and persists schedules (supports contiguous partial scheduling from stage 1).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function save(Procurement $procurement, array $items): array
    {
        if ($procurement->status === Procurement::STATUS_CANCELLED) {
            throw new DomainException('Cannot update schedule of cancelled procurement');
        }

        $schedules = $this->getOrderedSchedules($procurement);
        if ($schedules->isEmpty()) {
            throw new DomainException('Procurement has no stages to schedule');
        }

        $byId = $schedules->keyBy('id');
        $bySequence = $schedules->keyBy('sequence');
        $seenStageIds = [];

        foreach ($items as $idx => $item) {
            $target = null;
            if (isset($item['id']) && $item['id'] !== null) {
                $target = $byId->get((int) $item['id']);
                if (!$target) {
                    throw new DomainException("Schedule item at index {$idx} references invalid schedule id {$item['id']}");
                }
            } elseif (isset($item['sequence']) && $item['sequence'] !== null) {
                $target = $bySequence->get((int) $item['sequence']);
                if (!$target) {
                    throw new DomainException("Schedule item at index {$idx} references invalid sequence {$item['sequence']}");
                }
            } else {
                throw new DomainException("Schedule item at index {$idx} must include id or sequence");
            }

            if (isset($seenStageIds[$target->id])) {
                throw new DomainException("Duplicate schedule entry for stage #{$target->sequence} ({$target->name})");
            }
            $seenStageIds[$target->id] = true;

            if (array_key_exists('start_at', $item) || array_key_exists('end_at', $item)) {
                $rawStart = $item['start_at'] ?? null;
                $rawEnd = $item['end_at'] ?? null;

                $hasStart = $rawStart !== null && $rawStart !== '';
                $hasEnd = $rawEnd !== null && $rawEnd !== '';

                if ($hasStart !== $hasEnd) {
                    throw new DomainException(
                        "Stage #{$target->sequence} ({$target->name}): start_at and end_at must both be filled or both be null"
                    );
                }

                $target->start_at = $hasStart ? CarbonImmutable::parse($rawStart) : null;
                $target->end_at = $hasEnd ? CarbonImmutable::parse($rawEnd) : null;
            }

            if (array_key_exists('is_public', $item) && $item['is_public'] !== null) {
                $target->is_public = filter_var($item['is_public'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $holidaySet = StageSchedule::normalizeHolidaySet(Holiday::activeDateStrings());
        $otherSchedules = $this->otherActiveSchedules((int) $procurement->id);

        $seenUnscheduled = false;
        /** @var ?CarbonImmutable $prevEnd */
        $prevEnd = null;

        foreach ($schedules as $stage) {
            $seq = (int) $stage->sequence;
            $hasDates = $stage->start_at !== null && $stage->end_at !== null;

            if (!$hasDates) {
                if ($stage->start_at !== null || $stage->end_at !== null) {
                    throw new DomainException(
                        "Stage #{$seq} ({$stage->name}): start_at and end_at must both be filled or both be null"
                    );
                }
                $seenUnscheduled = true;
                continue;
            }

            if ($seenUnscheduled) {
                throw new DomainException(
                    "Stage #{$seq} ({$stage->name}): cannot schedule stage after an unscheduled earlier stage; fill sequentially from stage 1"
                );
            }

            $start = CarbonImmutable::instance($stage->start_at);
            $end = CarbonImmutable::instance($stage->end_at);

            if ($end->lte($start)) {
                throw new DomainException(
                    "Stage #{$seq} ({$stage->name}): end_at must be greater than start_at"
                );
            }

            if ($stage->day_type === ProcurementMethodStage::DAY_TYPE_WORKING_DAY) {
                if (!StageSchedule::isWorkingDay($start, $holidaySet)) {
                    throw new DomainException(
                        "Stage #{$seq} ({$stage->name}): start_at ({$start->toDateString()}) must fall on a working day"
                    );
                }
                if (!StageSchedule::isWorkingDay($end, $holidaySet)) {
                    throw new DomainException(
                        "Stage #{$seq} ({$stage->name}): end_at ({$end->toDateString()}) must fall on a working day"
                    );
                }
            }

            $actualDays = StageSchedule::countActualDays($start, $end, $stage->day_type, $holidaySet);
            if ($actualDays < 1 || $actualDays > (int) $stage->duration_days) {
                throw new DomainException(
                    "Stage #{$seq} ({$stage->name}): duration of {$actualDays} {$stage->day_type} day(s) exceeds maximum allowed {$stage->duration_days} day(s)"
                );
            }

            if ($prevEnd !== null && $start->startOfDay()->lte($prevEnd->startOfDay())) {
                throw new DomainException(
                    "Stage #{$seq} ({$stage->name}): start date ({$start->toDateString()}) clashes with previous stage end date ({$prevEnd->toDateString()})"
                );
            }

            $conflict = $this->findCrossProcurementConflict($start, $end, $otherSchedules);
            if ($conflict !== null) {
                $otherLabel = $conflict->procurement
                    ? "{$conflict->procurement->job_number} - {$conflict->procurement->name}"
                    : "Procurement #{$conflict->procurement_id}";
                $window = $conflict->start_at->format('Y-m-d H:i').' s/d '.$conflict->end_at->format('Y-m-d H:i');

                throw new DomainException(
                    "Stage #{$seq} ({$stage->name}): schedule clashes with stage '{$conflict->name}' ({$window}) on {$otherLabel}"
                );
            }

            $prevEnd = $end;
        }

        DB::transaction(function () use ($schedules) {
            foreach ($schedules as $stage) {
                $stage->save();
            }
        });

        return $schedules
            ->map(fn (ProcurementSchedule $s) => $s->toApiArray($holidaySet))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, true>  $holidaySet
     * @param  Collection<int, ProcurementSchedule>  $otherSchedules
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function findNextAvailableWindow(
        CarbonImmutable $initialCursor,
        int $durationDays,
        string $dayType,
        string $startTime,
        string $endTime,
        array $holidaySet,
        Collection $otherSchedules
    ): array {
        $cursor = $initialCursor->startOfDay();
        $isWorkingDay = $dayType === ProcurementMethodStage::DAY_TYPE_WORKING_DAY;
        $safety = 0;

        while (true) {
            $candidateStartDate = $isWorkingDay
                ? StageSchedule::nextWorkingDay($cursor, $holidaySet)
                : $cursor;

            $candidateEndDate = StageSchedule::endDateForDuration(
                $candidateStartDate,
                $durationDays,
                $dayType,
                $holidaySet
            );

            $candidateStartAt = $candidateStartDate->setTimeFromTimeString($startTime);
            $candidateEndAt = $candidateEndDate->setTimeFromTimeString($endTime);

            $maxConflictEnd = null;
            foreach ($otherSchedules as $other) {
                $otherStart = CarbonImmutable::instance($other->start_at);
                $otherEnd = CarbonImmutable::instance($other->end_at);

                if ($candidateStartAt->lt($otherEnd) && $candidateEndAt->gt($otherStart)) {
                    if ($maxConflictEnd === null || $otherEnd->gt($maxConflictEnd)) {
                        $maxConflictEnd = $otherEnd;
                    }
                }
            }

            if ($maxConflictEnd === null) {
                return [$candidateStartAt, $candidateEndAt];
            }

            $startHms = $candidateStartAt->format('H:i:s');
            $jumpDate = $maxConflictEnd->format('H:i:s') <= $startHms
                ? $maxConflictEnd->startOfDay()
                : $maxConflictEnd->startOfDay()->addDay();

            if ($jumpDate->lte($candidateStartDate)) {
                $jumpDate = $candidateStartDate->addDay();
            }

            $cursor = $jumpDate;

            if (++$safety > 3660) {
                throw new DomainException('Unable to locate conflict-free schedule window within search horizon');
            }
        }
    }

    /**
     * @param  Collection<int, ProcurementSchedule>  $otherSchedules
     */
    private function findCrossProcurementConflict(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        Collection $otherSchedules
    ): ?ProcurementSchedule {
        foreach ($otherSchedules as $other) {
            $otherStart = CarbonImmutable::instance($other->start_at);
            $otherEnd = CarbonImmutable::instance($other->end_at);

            if ($startAt->lt($otherEnd) && $endAt->gt($otherStart)) {
                return $other;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, ProcurementSchedule>
     */
    private function otherActiveSchedules(int $excludeProcurementId): Collection
    {
        return ProcurementSchedule::with('procurement:id,job_number,name,status,is_active')
            ->where('procurement_id', '<>', $excludeProcurementId)
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->whereHas('procurement', function ($query) {
                $query->where('is_active', true)
                    ->where('status', '<>', Procurement::STATUS_CANCELLED);
            })
            ->orderBy('start_at')
            ->get();
    }

    private function normalizeHms(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
