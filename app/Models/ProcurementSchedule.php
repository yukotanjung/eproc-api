<?php

namespace App\Models;

use App\Services\StageSchedule;
use App\Traits\ModelTrait;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementSchedule extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    public function getSequenceName(): string
    {
        return $this->getTable().'_'.$this->getKeyName().'_seq';
    }

    public function __isset($key)
    {
        if ($key === 'sequence') {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[1]['class'] ?? '';
            if (str_contains($trace, 'Yajra')) {
                return false;
            }
        }

        return parent::__isset($key);
    }

    public function __get($key)
    {
        if ($key === 'sequence') {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[1]['class'] ?? '';
            if (str_contains($trace, 'Yajra')) {
                return null;
            }
        }

        return parent::__get($key);
    }

    protected $casts = [
        'id'                          => 'integer',
        'procurement_id'              => 'integer',
        'procurement_method_stage_id' => 'integer',
        'sequence'                    => 'integer',
        'duration_days'               => 'integer',
        'requires_file'               => 'boolean',
        'is_public'                   => 'boolean',
        'start_at'                    => 'datetime',
        'end_at'                      => 'datetime',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function procurementMethodStage()
    {
        return $this->belongsTo(ProcurementMethodStage::class, 'procurement_method_stage_id');
    }

    /**
     * @param  array<string, true>  $holidaySet
     */
    public function computeActualDurationDays(array $holidaySet = []): ?int
    {
        if (!$this->start_at || !$this->end_at) {
            return null;
        }

        return StageSchedule::countActualDays(
            CarbonImmutable::instance($this->start_at),
            CarbonImmutable::instance($this->end_at),
            $this->day_type,
            $holidaySet
        );
    }

    /**
     * @param  array<string, true>  $holidaySet
     */
    public function toApiArray(array $holidaySet = []): array
    {
        return [
            'id'                          => $this->id,
            'procurement_id'              => (int) $this->procurement_id,
            'procurement_method_stage_id' => $this->procurement_method_stage_id !== null ? (int) $this->procurement_method_stage_id : null,
            'sequence'                    => (int) $this->sequence,
            'name'                        => $this->name,
            'duration_days'               => (int) $this->duration_days,
            'actual_duration_days'        => $this->computeActualDurationDays($holidaySet),
            'day_type'                    => $this->day_type,
            'requires_file'               => (bool) $this->requires_file,
            'is_public'                   => (bool) $this->is_public,
            'page_key'                    => $this->page_key,
            'process_key'                 => $this->process_key,
            'description'                 => $this->description,
            'start_at'                    => $this->start_at ? $this->start_at->format('Y-m-d H:i') : null,
            'end_at'                      => $this->end_at ? $this->end_at->format('Y-m-d H:i') : null,
            'created_at'                  => $this->created_at,
            'updated_at'                  => $this->updated_at,
        ];
    }

    /**
     * @param  array<string, true>  $holidaySet
     */
    public function toPublicApiArray(array $holidaySet = []): array
    {
        return [
            'id'                   => $this->id,
            'sequence'             => (int) $this->sequence,
            'name'                 => $this->name,
            'duration_days'        => (int) $this->duration_days,
            'actual_duration_days' => $this->computeActualDurationDays($holidaySet),
            'day_type'             => $this->day_type,
            'description'          => $this->description,
            'start_at'             => $this->start_at ? $this->start_at->format('Y-m-d H:i') : null,
            'end_at'               => $this->end_at ? $this->end_at->format('Y-m-d H:i') : null,
        ];
    }
}
