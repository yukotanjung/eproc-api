<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ProcurementMethodStage extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public const DAY_TYPE_CALENDAR = 'calendar';
    public const DAY_TYPE_WORKING_DAY = 'working_day';
    public const DAY_TYPES = [self::DAY_TYPE_CALENDAR, self::DAY_TYPE_WORKING_DAY];

    protected $guarded = [];

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
        'sequence'      => 'integer',
        'duration_days' => 'integer',
        'requires_file' => 'boolean',
        'is_active'     => 'boolean',
    ];

    public $filterable = [
        'name',
    ];

    public function procurementMethod()
    {
        return $this->belongsTo(ProcurementMethod::class, 'procurement_method_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'                    => $this->id,
            'procurement_method_id' => $this->procurement_method_id,
            'sequence'              => (int) $this->sequence,
            'name'                  => $this->name,
            'duration_days'         => (int) $this->duration_days,
            'day_type'              => $this->day_type,
            'requires_file'         => (bool) $this->requires_file,
            'page_key'              => $this->page_key,
            'process_key'           => $this->process_key,
            'description'           => $this->description,
            'is_active'             => (bool) $this->is_active,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }

    public static function nextSequence(int $procurementMethodId): int
    {
        return (int) static::where('procurement_method_id', $procurementMethodId)->max('sequence') + 1;
    }

    /** @return int[] */
    public static function orderedIds(int $procurementMethodId): array
    {
        return static::where('procurement_method_id', $procurementMethodId)
            ->orderBy('sequence')
            ->pluck('id')
            ->all();
    }

    /**
     * Rewrites `sequence` to 1..N following $orderedIds.
     *
     * Oracle validates pms_method_seq_uq at the end of every statement, so a direct
     * swap (1->2 while 2 still exists) raises ORA-00001. Negating first frees every slot.
     *
     * @param  int[]  $orderedIds
     */
    public static function reorderWithin(int $procurementMethodId, array $orderedIds): void
    {
        $orderedIds = array_values($orderedIds);
        if ($orderedIds === []) {
            return;
        }

        static::withTrashed()
            ->where('procurement_method_id', $procurementMethodId)
            ->whereIn('id', $orderedIds)
            ->update(['sequence' => DB::raw('-"sequence"')]);

        foreach ($orderedIds as $index => $id) {
            static::withTrashed()->where('id', $id)->update(['sequence' => $index + 1]);
        }
    }
}
