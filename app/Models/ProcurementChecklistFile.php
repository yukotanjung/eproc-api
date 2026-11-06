<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementChecklistFile extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar', 'dwg',
    ];

    public $incrementing = true;
    protected $keyType = 'int';
    protected $sequence = 'procurement_checklist_files_id_seq';

    protected $guarded = [];

    public function getSequenceName(): string
    {
        return $this->getTable().'_'.$this->getKeyName().'_seq';
    }

    protected $casts = [
        'id'                      => 'integer',
        'procurement_id'          => 'integer',
        'procurement_schedule_id' => 'integer',
        'max_size'                => 'integer',
        'file_types'              => 'array',
        'is_required'             => 'boolean',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ProcurementSchedule::class, 'procurement_schedule_id');
    }

    /**
     * @param  array<int, mixed>  $types
     * @return array<int, string>
     */
    public static function normalizeFileTypes(array $types): array
    {
        return array_values(array_unique(array_map(
            fn ($ext) => strtolower(ltrim(trim((string) $ext), '.')),
            $types
        )));
    }

    public function toApiArray(): array
    {
        return [
            'id'                      => (int) $this->id,
            'procurement_id'          => (int) $this->procurement_id,
            'procurement_schedule_id' => (int) $this->procurement_schedule_id,
            'name'                    => $this->name,
            'max_size'                => (int) $this->max_size,
            'file_types'              => array_values($this->file_types ?? []),
            'is_required'             => (bool) $this->is_required,
            'description'             => $this->description,
            'schedule'                => $this->schedule ? [
                'id'            => (int) $this->schedule->id,
                'sequence'      => (int) $this->schedule->sequence,
                'name'          => $this->schedule->name,
                'requires_file' => (bool) $this->schedule->requires_file,
            ] : null,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
