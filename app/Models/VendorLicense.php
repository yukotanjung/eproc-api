<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorLicense extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public const TYPES = ['SIUP', 'SIUJK', 'SBU'];

    protected $table = 'vendor_licenses';

    protected $guarded = [];

    protected $casts = [
        'vendor_id'    => 'integer',
        'issued_date'  => 'date:Y-m-d',
        'expired_date' => 'date:Y-m-d',
    ];

    public $filterable = [
        'license_type',
        'license_number',
        'issuing_agency',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeGetVendorLicense($query, $request, $page, $pageSize, $all, $vendorId = null)
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
            'id'             => (int) $this->id,
            'vendor_id'      => (int) $this->vendor_id,
            'license_type'   => $this->license_type,
            'license_number' => $this->license_number,
            'issued_date'    => $this->issued_date ? $this->issued_date->format('Y-m-d') : null,
            'expired_date'   => $this->expired_date ? $this->expired_date->format('Y-m-d') : null,
            'issuing_agency' => $this->issuing_agency,
            'file_path'      => $this->file_path,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
