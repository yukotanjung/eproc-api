<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $filterable = [
        'npwp',
        'company_name',
        'phone',
        'pic',
        'email',
        'address',
        'province_id',
        'city_id',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function users()
    {
        return $this->hasMany(VendorUser::class, 'vendor_id');
    }

    public function owners()
    {
        return $this->hasMany(VendorOwner::class, 'vendor_id');
    }

    public function commissioners()
    {
        return $this->hasMany(VendorCommissioner::class, 'vendor_id');
    }

    public function directors()
    {
        return $this->hasMany(VendorDirector::class, 'vendor_id');
    }

    public function experts()
    {
        return $this->hasMany(VendorExpert::class, 'vendor_id');
    }

    public function financials()
    {
        return $this->hasMany(VendorFinancial::class, 'vendor_id');
    }

    public function taxes()
    {
        return $this->hasMany(VendorTax::class, 'vendor_id');
    }

    public function legals()
    {
        return $this->hasMany(VendorLegal::class, 'vendor_id');
    }

    public function scopeGetVendor($query, $request, $page, $pageSize, $all)
    {
        $data = $query->with(['province', 'city'])->withCount('users')->filter()->order();
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

        $cityId = $filter['city_id'] ?? request()->input('city_id');
        if (!empty($cityId) && empty($filter['city_id'])) {
            $query->where('city_id', $cityId);
        }

        return $query;
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

    public function toApiArray(bool $includeUsers = false): array
    {
        $payload = [
            'id'           => (int) $this->id,
            'npwp'         => $this->npwp,
            'company_name' => $this->company_name,
            'phone'        => $this->phone,
            'pic'          => $this->pic,
            'email'        => $this->email,
            'address'      => $this->address,
            'province_id'  => $this->province_id ? (int) $this->province_id : null,
            'city_id'      => $this->city_id ? (int) $this->city_id : null,
            'is_active'    => (bool) $this->is_active,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'province'     => $this->province ? [
                'id'   => (int) $this->province->id,
                'name' => $this->province->name,
            ] : null,
            'city'         => $this->city ? [
                'id'          => (int) $this->city->id,
                'province_id' => (int) $this->city->province_id,
                'name'        => $this->city->name,
            ] : null,
        ];

        if (isset($this->users_count)) {
            $payload['users_count'] = (int) $this->users_count;
        }

        if ($includeUsers && $this->relationLoaded('users')) {
            $payload['users'] = $this->users->map(fn (VendorUser $u) => $u->toApiArray())->values();
        }

        return $payload;
    }
}
