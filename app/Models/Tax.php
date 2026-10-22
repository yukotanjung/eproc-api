<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    public $filterable = [
        'name',
    ];

    public function scopeGetTax($query, $request, $page, $pageSize, $all)
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
