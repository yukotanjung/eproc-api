<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorExperience extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $table = 'vendor_experiences';

    protected $guarded = [];

    protected $casts = [
        'vendor_id'         => 'integer',
        'job_type_id'       => 'integer',
        'contract_value'    => 'float',
        'contract_date'     => 'date:Y-m-d',
        'contract_end_date' => 'date:Y-m-d',
        'handover_date'     => 'date:Y-m-d',
    ];

    public $filterable = [
        'job_name',
        'contract_number',
        'client_name',
        'location',
        'handover_number',
        'job_type_id',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function jobType()
    {
        return $this->belongsTo(JobType::class, 'job_type_id');
    }

    public function scopeGetVendorExperience($query, $request, $page, $pageSize, $all, $vendorId = null)
    {
        $data = $query->with('jobType')->filter();
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
            'id'                 => (int) $this->id,
            'vendor_id'          => (int) $this->vendor_id,
            'job_name'           => $this->job_name,
            'contract_number'    => $this->contract_number,
            'contract_value'     => (float) $this->contract_value,
            'job_type_id'        => (int) $this->job_type_id,
            'job_type'           => $this->jobType ? [
                'id'   => (int) $this->jobType->id,
                'name' => $this->jobType->name,
            ] : null,
            'client_name'        => $this->client_name,
            'location'           => $this->location,
            'address'            => $this->address,
            'contract_date'      => $this->contract_date ? $this->contract_date->format('Y-m-d') : null,
            'contract_end_date'  => $this->contract_end_date ? $this->contract_end_date->format('Y-m-d') : null,
            'contract_file_path' => $this->contract_file_path,
            'handover_date'      => $this->handover_date ? $this->handover_date->format('Y-m-d') : null,
            'handover_number'    => $this->handover_number,
            'handover_file_path' => $this->handover_file_path,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
