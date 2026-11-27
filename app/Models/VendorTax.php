<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorTax extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $table = 'vendor_taxes';

    protected $guarded = [];

    protected $casts = [
        'vendor_id'   => 'integer',
        'tax_id'      => 'integer',
        'report_date' => 'date:Y-m-d',
    ];

    public $filterable = [
        'report_date',
        'tax_id',
        'receipt_number',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function scopeGetVendorTax($query, $request, $page, $pageSize, $all, $vendorId = null)
    {
        $data = $query->with('tax')->filter();
        if ($vendorId !== null) {
            $data->where('vendor_id', $vendorId);
        }
        $data->order();

        if ($all == 'true' || $all === true) {
            return $data->orderBy('report_date', 'desc')->get();
        }
        return $data->orderBy('report_date', 'desc')->paginate($pageSize, ['*'], 'page', $page);
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
            'report_date'    => $this->report_date ? $this->report_date->format('Y-m-d') : null,
            'tax_id'         => (int) $this->tax_id,
            'tax'            => $this->tax ? [
                'id'     => (int) $this->tax->id,
                'name'   => $this->tax->name,
                'is_spt' => (bool) $this->tax->is_spt,
            ] : null,
            'receipt_number' => $this->receipt_number,
            'receipt_path'   => $this->receipt_path,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
