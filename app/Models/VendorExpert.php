<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorExpert extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $table = 'vendor_experts';

    protected $guarded = [];

    protected $casts = [
        'vendor_id'          => 'integer',
        'education_level_id' => 'integer',
        'experience_years'   => 'integer',
        'birth_date'         => 'date:Y-m-d',
    ];

    public $filterable = [
        'name',
        'position',
        'npwp_number',
        'ktp_number',
        'major',
        'diploma_number',
        'education_level_id',
        'vendor_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function educationLevel()
    {
        return $this->belongsTo(EducationLevel::class, 'education_level_id');
    }

    public function scopeGetVendorExpert($query, $request, $page, $pageSize, $all, $vendorId = null)
    {
        $data = $query->with('educationLevel')->filter();
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
            'name'               => $this->name,
            'birth_place'        => $this->birth_place,
            'birth_date'         => $this->birth_date ? $this->birth_date->format('Y-m-d') : null,
            'position'           => $this->position,
            'npwp_number'        => $this->npwp_number,
            'ktp_number'         => $this->ktp_number,
            'address'            => $this->address,
            'education_level_id' => (int) $this->education_level_id,
            'education_level'    => $this->educationLevel ? [
                'id'   => (int) $this->educationLevel->id,
                'name' => $this->educationLevel->name,
            ] : null,
            'major'              => $this->major,
            'diploma_number'     => $this->diploma_number,
            'experience_years'   => (int) $this->experience_years,
            'photo_path'         => $this->photo_path,
            'diploma_path'       => $this->diploma_path,
            'certificate_path'   => $this->certificate_path,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
