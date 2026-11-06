<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementHpsItem extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public const DEFAULT_TAX_RATE = 11.0;

    public $incrementing = true;
    protected $keyType = 'int';
    protected $sequence = 'procurement_hps_items_id_seq';

    protected $guarded = [];

    protected $casts = [
        'id'             => 'integer',
        'procurement_id' => 'integer',
        'unit_id'        => 'integer',
        'quantity'       => 'integer',
        'unit_price'     => 'float',
        'use_tax'        => 'boolean',
        'tax_rate'       => 'float',
        'tax_amount'     => 'float',
        'subtotal'       => 'float',
    ];

    public function getSequenceName(): string
    {
        return $this->getTable().'_'.$this->getKeyName().'_seq';
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * @return array{base_subtotal: float, tax_rate: float, tax_amount: float, subtotal: float}
     */
    public static function calculateAmounts(int $quantity, float $unitPrice, bool $useTax, float $taxRate = self::DEFAULT_TAX_RATE): array
    {
        $baseSubtotal = round($quantity * $unitPrice, 2);
        $appliedRate = $useTax ? $taxRate : 0.0;
        $taxAmount = $useTax ? round($baseSubtotal * ($taxRate / 100), 2) : 0.0;
        $subtotal = round($baseSubtotal + $taxAmount, 2);

        return [
            'base_subtotal' => $baseSubtotal,
            'tax_rate'      => $appliedRate,
            'tax_amount'    => $taxAmount,
            'subtotal'      => $subtotal,
        ];
    }

    public function toApiArray(): array
    {
        $baseSubtotal = round(((int) $this->quantity) * ((float) $this->unit_price), 2);

        return [
            'id'             => (int) $this->id,
            'procurement_id' => (int) $this->procurement_id,
            'unit_id'        => (int) $this->unit_id,
            'name'           => $this->name,
            'quantity'       => (int) $this->quantity,
            'unit_price'     => (float) $this->unit_price,
            'base_subtotal'  => $baseSubtotal,
            'use_tax'        => (bool) $this->use_tax,
            'tax_rate'       => (float) $this->tax_rate,
            'tax_amount'     => (float) $this->tax_amount,
            'subtotal'       => (float) $this->subtotal,
            'unit'           => $this->unit ? [
                'id'   => (int) $this->unit->id,
                'name' => $this->unit->name,
                'code' => $this->unit->code,
            ] : null,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
