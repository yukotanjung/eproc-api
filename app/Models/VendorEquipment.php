<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorEquipment extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public const CONDITIONS = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    protected $table = 'vendor_equipments';

    protected $guarded = [];

    protected $casts = [
        'vendor_id'        => 'integer',
        'quantity'         => 'integer',
        'manufacture_year' => 'integer',
    ];

    public $filterable = [
        'equipment_type',
        'brand',
        'condition',
        'location',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeGetVendorEquipment($query, $request, $page, $pageSize, $all, $vendorId = null)
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
            'id'               => (int) $this->id,
            'vendor_id'        => (int) $this->vendor_id,
            'equipment_type'   => $this->equipment_type,
            'brand'            => $this->brand,
            'quantity'         => (int) $this->quantity,
            'capacity'         => $this->capacity,
            'manufacture_year' => (int) $this->manufacture_year,
            'condition'        => $this->condition,
            'location'         => $this->location,
            'photo_path'       => $this->photo_path,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
