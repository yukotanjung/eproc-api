<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobSubField extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    public $filterable = [
        'name',
        'job_type_id',
        'job_field_id',
    ];

    public function jobType()
    {
        return $this->belongsTo(JobType::class, 'job_type_id');
    }

    public function jobField()
    {
        return $this->belongsTo(JobField::class, 'job_field_id');
    }

    public function procurements()
    {
        return $this->belongsToMany(Procurement::class, 'procurement_job_sub_fields', 'job_sub_field_id', 'procurement_id')
            ->withTimestamps();
    }

    public function scopeGetJobSubField($query, $request, $page, $pageSize, $all)
    {
        $data = $query->with(['jobType', 'jobField'])->filter()->order();
        if ($all == 'true') {
            return $data->orderBy('created_at', 'desc')->get();
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

    public function toApiArray(): array
    {
        return [
            'id'           => (int) $this->id,
            'name'         => $this->name,
            'job_type_id'  => (int) $this->job_type_id,
            'job_field_id' => (int) $this->job_field_id,
            'is_active'    => (bool) $this->is_active,
            'job_type'     => $this->jobType ? [
                'id'   => (int) $this->jobType->id,
                'name' => $this->jobType->name,
            ] : null,
            'job_field'    => $this->jobField ? [
                'id'   => (int) $this->jobField->id,
                'name' => $this->jobField->name,
            ] : null,
        ];
    }
}
