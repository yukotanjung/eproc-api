<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $filterable = [
        'name',
        'province_id',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function vendors()
    {
        return $this->hasMany(Vendor::class, 'city_id');
    }

    public function scopeGetCity($query, $request, $page, $pageSize, $all)
    {
        $data = $query->with('province')->filter();

        if ($request && method_exists($request, 'input')) {
            $directProvinceId = $request->input('province_id');
            if (!empty($directProvinceId) && empty(request()->input('filter.province_id')) && empty(request()->input('province_id'))) {
                $data->where('province_id', $directProvinceId);
            }
        }

        $data->order();

        if ($all == 'true' || $all === true) {
            return $data->orderBy('created_at', 'desc')->get();
        }
        return $data->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function scopeFilter($query)
    {
        $this->filter_model($query, $this);

        $filter = request()->input('filter', []);
        $provinceId = $filter['province_id'] ?? request()->input('province_id');
        if (!empty($provinceId) && empty($filter['province_id'])) {
            $query->where('province_id', $provinceId);
        }

        return $query;
    }

    public function scopeByProvince($query, $provinceId)
    {
        if (!empty($provinceId)) {
            return $query->where('province_id', $provinceId);
        }
        return $query;
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }
}
