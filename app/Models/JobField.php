<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobField extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    public $filterable = [
        'name',
        'job_type_id',
    ];

    public function jobType()
    {
        return $this->belongsTo(JobType::class, 'job_type_id');
    }

    public function classifications()
    {
        return $this->hasMany(Classification::class, 'job_field_id');
    }

    public function scopeGetJobField($query, $request, $page, $pageSize, $all)
    {
        $data = $query->with('jobType')->filter()->order();
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
}
