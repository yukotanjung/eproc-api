<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementDocument extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public $incrementing = true;
    protected $keyType = 'int';
    protected $sequence = 'procurement_documents_id_seq';

    protected $guarded = [];

    protected $casts = [
        'id'             => 'integer',
        'procurement_id' => 'integer',
        'file_size'      => 'integer',
        'is_public'      => 'boolean',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'             => (int) $this->id,
            'procurement_id' => (int) $this->procurement_id,
            'name'           => $this->name,
            'file_name'      => $this->file_name,
            'mime_type'      => $this->mime_type,
            'file_size'      => $this->file_size !== null ? (int) $this->file_size : null,
            'extension'      => $this->extension,
            'is_public'      => (bool) $this->is_public,
            'download_url'   => url("/api/procurement/{$this->procurement_id}/document/{$this->id}/download"),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }

    public function toPublicApiArray(): array
    {
        return [
            'id'           => (int) $this->id,
            'name'         => $this->name,
            'file_name'    => $this->file_name,
            'mime_type'    => $this->mime_type,
            'file_size'    => $this->file_size !== null ? (int) $this->file_size : null,
            'extension'    => $this->extension,
            'download_url' => url("/api/public/procurement/{$this->procurement_id}/document/{$this->id}/download"),
            'created_at'   => $this->created_at,
        ];
    }
}
