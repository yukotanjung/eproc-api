<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procurement extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CANCELLED = 'cancelled';

    public $incrementing = true;
    protected $keyType = 'int';
    protected $sequence = 'procurements_id_seq';

    protected $guarded = [];

    protected $casts = [
        'id'              => 'integer',
        'budget_ceiling'  => 'float',
        'estimated_price' => 'float',
        'budget_year'     => 'integer',
        'planned_date'    => 'date:Y-m-d',
        'cancelled_at'    => 'datetime',
        'is_active'       => 'boolean',
    ];

    public $filterable = [
        'job_number',
        'name',
        'budget_year',
        'project_location',
        'announcement_location',
        'job_type_id',
        'qualification_id',
        'funding_source_id',
        'procurement_method_id',
        'status',
    ];

    public function jobType()
    {
        return $this->belongsTo(JobType::class, 'job_type_id');
    }

    public function qualification()
    {
        return $this->belongsTo(Qualification::class, 'qualification_id');
    }

    public function fundingSource()
    {
        return $this->belongsTo(FundingSource::class, 'funding_source_id');
    }

    public function procurementMethod()
    {
        return $this->belongsTo(ProcurementMethod::class, 'procurement_method_id');
    }

    public function scopeGetProcurement($query, $request, $page, $pageSize, $all)
    {
        $data = $query->with([
            'jobType',
            'qualification',
            'procurementMethod',
        ])->filter()->order();

        if ($all) {
            return $data->orderBy('created_at', 'desc')->get();
        }

        return $data->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function scopeFilter($query)
    {
        $this->filter_model($query, $this);

        $filter = request()->input('filter', []);

        $year = $filter['tahun'] ?? request()->input('tahun') ?? request()->input('budget_year');
        if (!empty($year) && empty($filter['budget_year'])) {
            $query->where('budget_year', $year);
        }

        $jobType = $filter['jenis_pekerjaan'] ?? $filter['job_type'] ?? request()->input('jenis_pekerjaan') ?? request()->input('job_type');
        if (!empty($jobType) && empty($filter['job_type_id'])) {
            if (is_numeric($jobType)) {
                $query->where('job_type_id', $jobType);
            } else {
                $query->whereHas('jobType', fn($q) => $q->where('name', 'like', '%' . $jobType . '%'));
            }
        } elseif (!empty($filter['job_type_id']) && !is_numeric($filter['job_type_id'])) {
            $query->whereHas('jobType', fn($q) => $q->where('name', 'like', '%' . $filter['job_type_id'] . '%'));
        }

        $procMethod = $filter['metode_pengadaan'] ?? $filter['procurement_method'] ?? request()->input('metode_pengadaan') ?? request()->input('procurement_method');
        if (!empty($procMethod) && empty($filter['procurement_method_id'])) {
            if (is_numeric($procMethod)) {
                $query->where('procurement_method_id', $procMethod);
            } else {
                $query->whereHas('procurementMethod', fn($q) => $q->where('name', 'like', '%' . $procMethod . '%'));
            }
        } elseif (!empty($filter['procurement_method_id']) && !is_numeric($filter['procurement_method_id'])) {
            $query->whereHas('procurementMethod', fn($q) => $q->where('name', 'like', '%' . $filter['procurement_method_id'] . '%'));
        }

        $qualification = $filter['kualifikasi'] ?? $filter['qualification'] ?? request()->input('kualifikasi') ?? request()->input('qualification');
        if (!empty($qualification) && empty($filter['qualification_id'])) {
            if (is_numeric($qualification)) {
                $query->where('qualification_id', $qualification);
            } else {
                $query->whereHas('qualification', fn($q) => $q->where('name', 'like', '%' . $qualification . '%'));
            }
        } elseif (!empty($filter['qualification_id']) && !is_numeric($filter['qualification_id'])) {
            $query->whereHas('qualification', fn($q) => $q->where('name', 'like', '%' . $filter['qualification_id'] . '%'));
        }

        $name = request()->input('name');
        if (!empty($name) && empty($filter['name'])) {
            $query->where('name', 'like', '%' . $name . '%');
        }

        return $query;
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

    public function toListApiArray(): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'budget_ceiling'     => (float) $this->budget_ceiling,
            'estimated_price'    => (float) $this->estimated_price,
            'budget_year'        => (int) $this->budget_year,
            'job_type'           => $this->jobType ? [
                'id'   => $this->jobType->id,
                'name' => $this->jobType->name,
            ] : null,
            'procurement_method' => $this->procurementMethod ? [
                'id'   => $this->procurementMethod->id,
                'name' => $this->procurementMethod->name,
            ] : null,
            'qualification'      => $this->qualification ? [
                'id'   => $this->qualification->id,
                'name' => $this->qualification->name,
            ] : null,
            'status'             => $this->status,

        ];
    }

    public function toApiArray(): array
    {
        return [
            'id'                    => $this->id,
            'job_number'            => $this->job_number,
            'name'                  => $this->name,
            'budget_ceiling'        => (float) $this->budget_ceiling,
            'estimated_price'       => (float) $this->estimated_price,
            'planned_date'          => $this->planned_date ? $this->planned_date->format('Y-m-d') : null,
            'job_type_id'           => $this->job_type_id,
            'qualification_id'      => $this->qualification_id,
            'funding_source_id'     => $this->funding_source_id,
            'budget_year'           => (int) $this->budget_year,
            'project_location'      => $this->project_location,
            'procurement_method_id' => $this->procurement_method_id,
            'announcement_location' => $this->announcement_location,
            'is_active'             => (bool) $this->is_active,
            'status'                => $this->status,
            'cancellation_reason'   => $this->cancellation_reason,
            'cancelled_at'          => $this->cancelled_at ? $this->cancelled_at->format('Y-m-d H:i:s') : null,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
            'job_type'              => $this->jobType ? [
                'id'   => $this->jobType->id,
                'name' => $this->jobType->name,
            ] : null,
            'qualification'         => $this->qualification ? [
                'id'   => $this->qualification->id,
                'name' => $this->qualification->name,
            ] : null,
            'funding_source'        => $this->fundingSource ? [
                'id'   => $this->fundingSource->id,
                'name' => $this->fundingSource->name,
            ] : null,
            'procurement_method'    => $this->procurementMethod ? [
                'id'   => $this->procurementMethod->id,
                'name' => $this->procurementMethod->name,
            ] : null,
        ];
    }
}
