<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorDirector extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $table = 'vendor_directors';

    protected $guarded = [];

    protected $casts = [
        'vendor_id' => 'integer',
        'is_pic'    => 'boolean',
    ];

    public $filterable = [
        'name',
        'ktp_number',
        'npwp_number',
        'position',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeGetVendorDirector($query, $request, $page, $pageSize, $all, $vendorId = null)
    {
        $data = $query->filter();
        if ($vendorId !== null) {
            $data->where('vendor_id', $vendorId);
        }
        $data->order();

        if ($all == 'true' || $all === true) {
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
            'id'          => (int) $this->id,
            'vendor_id'   => (int) $this->vendor_id,
            'name'        => $this->name,
            'ktp_number'  => $this->ktp_number,
            'npwp_number' => $this->npwp_number,
            'position'    => $this->position,
            'is_pic'      => (bool) $this->is_pic,
            'photo_path'  => $this->photo_path,
            'npwp_path'   => $this->npwp_path,
            'ktp_path'    => $this->ktp_path,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
