<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementMethod extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $filterable = [
        'name',
        'description',
    ];

    // No orderBy here: withCount() would put the order inside the aggregate subquery.
    public function stages()
    {
        return $this->hasMany(ProcurementMethodStage::class, 'procurement_method_id');
    }

    public function scopeGetProcurementMethod($query, $request, $page, $pageSize, $all)
    {
        $data = $query->withCount('stages')->filter()->order();
        if ($all) {
            return $data->orderBy('name')->get();
        }
        return $data->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function scopeFilter($query)
    {
        return $this->filter_model($query, $this);
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

    public function toApiArray(bool $withStages = false): array
    {
        $payload = [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'is_active'    => (bool) $this->is_active,
            'stages_count' => $this->stages_count
                ?? ($this->relationLoaded('stages') ? $this->stages->count() : $this->stages()->count()),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];

        if ($withStages) {
            $payload['stages'] = $this->stages
                ->sortBy('sequence')
                ->values()
                ->map(fn (ProcurementMethodStage $stage) => $stage->toApiArray())
                ->all();
        }

        return $payload;
    }
}
