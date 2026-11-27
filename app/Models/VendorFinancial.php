<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorFinancial extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $table = 'vendor_financials';

    protected $guarded = [];

    protected $casts = [
        'vendor_id'              => 'integer',
        'report_date'            => 'date:Y-m-d',
        'fixed_assets'           => 'float',
        'current_assets'         => 'float',
        'other_assets'           => 'float',
        'long_term_liabilities'  => 'float',
        'short_term_liabilities' => 'float',
        'capital'                => 'float',
        'net_worth'              => 'float',
    ];

    public $filterable = [
        'report_date',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeGetVendorFinancial($query, $request, $page, $pageSize, $all, $vendorId = null)
    {
        $data = $query->filter();
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
            'id'                     => (int) $this->id,
            'vendor_id'              => (int) $this->vendor_id,
            'report_date'            => $this->report_date ? $this->report_date->format('Y-m-d') : null,
            'fixed_assets'           => (float) $this->fixed_assets,
            'current_assets'         => (float) $this->current_assets,
            'other_assets'           => (float) $this->other_assets,
            'long_term_liabilities'  => (float) $this->long_term_liabilities,
            'short_term_liabilities' => (float) $this->short_term_liabilities,
            'capital'                => (float) $this->capital,
            'net_worth'              => (float) $this->net_worth,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}
