<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public $incrementing = true;
    protected $keyType = 'int';
    protected $sequence = 'units_id_seq';

    protected $guarded = [];

    protected $casts = [
        'id'        => 'integer',
        'is_active' => 'boolean',
    ];

    public $filterable = [
        'name',
        'code',
    ];

    public function getSequenceName(): string
    {
        return $this->getTable().'_'.$this->getKeyName().'_seq';
    }

    public function hpsItems()
    {
        return $this->hasMany(ProcurementHpsItem::class, 'unit_id');
    }

    public function scopeGetUnit($query, $request, $page, $pageSize, $all)
    {
        $data = $query->filter()->order();
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
}
