<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorLegal extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $table = 'vendor_legals';

    protected $guarded = [];

    protected $casts = [
        'vendor_id'          => 'integer',
        'establishment_date' => 'date:Y-m-d',
        'amendment_date'     => 'date:Y-m-d',
    ];

    public $filterable = [
        'establishment_deed_number',
        'establishment_notary',
        'amendment_deed_number',
        'amendment_notary',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeGetVendorLegal($query, $request, $page, $pageSize, $all, $vendorId = null)
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
            'id'                        => (int) $this->id,
            'vendor_id'                 => (int) $this->vendor_id,
            'establishment_date'        => $this->establishment_date ? $this->establishment_date->format('Y-m-d') : null,
            'establishment_deed_number' => $this->establishment_deed_number,
            'establishment_notary'      => $this->establishment_notary,
            'establishment_deed_path'   => $this->establishment_deed_path,
            'amendment_date'            => $this->amendment_date ? $this->amendment_date->format('Y-m-d') : null,
            'amendment_deed_number'     => $this->amendment_deed_number,
            'amendment_notary'          => $this->amendment_notary,
            'amendment_deed_path'       => $this->amendment_deed_path,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
        ];
    }
}
