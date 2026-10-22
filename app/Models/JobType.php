<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobType extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    public $filterable = [
        'name',
    ];

    public function jobFields()
    {
        return $this->hasMany(JobField::class, 'job_type_id');
    }

    public function classifications()
    {
        return $this->hasMany(Classification::class, 'job_type_id');
    }

    public function scopeGetJobType($query, $request, $page, $pageSize, $all)
    {
        $data = $query->filter()->order();
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
