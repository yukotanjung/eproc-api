<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'id'           => 'integer',
        'holiday_date' => 'date:Y-m-d',
        'is_active'    => 'boolean',
    ];

    public $filterable = [
        'name',
        'description',
    ];

    public function scopeGetHoliday($query, $request, $page, $pageSize, $all)
    {
        $data = $query->filter()->order();

        if ($all) {
            return $data->orderBy('holiday_date', 'asc')->get();
        }

        return $data->orderBy('holiday_date', 'asc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function scopeFilter($query)
    {
        $this->filter_model($query, $this);

        $filter = request()->input('filter', []);
        $year = $filter['year'] ?? $filter['tahun'] ?? request()->input('year') ?? request()->input('tahun');
        if (!empty($year) && is_numeric($year)) {
            $yearInt = (int) $year;
            $query->whereBetween('holiday_date', ["{$yearInt}-01-01", "{$yearInt}-12-31"]);
        }

        $holidayDate = $filter['holiday_date'] ?? request()->input('holiday_date');
        if (!empty($holidayDate)) {
            $query->whereDate('holiday_date', $holidayDate);
        }

        return $query;
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

    /**
     * @return string[]
     */
    public static function activeDateStrings(): array
    {
        return static::where('is_active', true)
            ->get(['holiday_date'])
            ->map(fn (self $h) => $h->holiday_date->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();
    }

    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'holiday_date' => $this->holiday_date ? $this->holiday_date->format('Y-m-d') : null,
            'name'         => $this->name,
            'description'  => $this->description,
            'is_active'    => (bool) $this->is_active,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
