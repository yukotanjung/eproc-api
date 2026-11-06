<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\ProcurementHpsItem;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementHpsController extends Controller
{
    use RuleTrait;

    public function index($id)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $items = $procurement->hpsItems()
            ->with('unit')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcurementHpsItem $item) => $item->toApiArray())
            ->values()
            ->all();

        $totalBeforeTax = round(array_sum(array_column($items, 'base_subtotal')), 2);
        $totalTax = round(array_sum(array_column($items, 'tax_amount')), 2);
        $grandTotal = round(array_sum(array_column($items, 'subtotal')), 2);

        return $this->baseResponse('Success get procurement hps', [
            'items'   => $items,
            'summary' => [
                'total_items'      => count($items),
                'total_before_tax' => $totalBeforeTax,
                'total_tax'        => $totalTax,
                'grand_total'      => $grandTotal,
            ],
        ]);
    }

    public function show($id, $hpsId)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $item = $procurement->hpsItems()
            ->with('unit')
            ->find($hpsId);

        if (!$item) {
            return $this->invalidResponse('No data');
        }

        return $this->baseResponse('Success get procurement hps item', $item->toApiArray());
    }

    public function store(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) {
                return $this->errorResponses($input_error);
            }

            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            if ($procurement->isCancelled()) {
                return $this->errorValidateResponse('Cannot update cancelled procurement');
            }

            $quantity = (int) $request->input('quantity');
            $unitPrice = (float) $request->input('unit_price');
            $useTax = $request->has('use_tax') && $request->input('use_tax') !== null
                ? filter_var($request->input('use_tax'), FILTER_VALIDATE_BOOLEAN)
                : false;

            $amounts = ProcurementHpsItem::calculateAmounts($quantity, $unitPrice, $useTax);

            DB::beginTransaction();

            $hpsItem = ProcurementHpsItem::create([
                'procurement_id' => $procurement->id,
                'unit_id'        => (int) $request->input('unit_id'),
                'name'           => $request->input('name'),
                'quantity'       => $quantity,
                'unit_price'     => $unitPrice,
                'use_tax'        => $useTax,
                'tax_rate'       => $amounts['tax_rate'],
                'tax_amount'     => $amounts['tax_amount'],
                'subtotal'       => $amounts['subtotal'],
            ]);

            DB::commit();

            return $this->baseResponse(
                'Success create procurement hps item',
                $hpsItem->load('unit')->toApiArray()
            );
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function update(Request $request, $id, $hpsId)
    {
        try {
            if ($input_error = $this->check_input_validation()) {
                return $this->errorResponses($input_error);
            }

            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            if ($procurement->isCancelled()) {
                return $this->errorValidateResponse('Cannot update cancelled procurement');
            }

            $hpsItem = $procurement->hpsItems()->find($hpsId);
            if (!$hpsItem) {
                return $this->invalidResponse('No data');
            }

            $quantity = (int) $request->input('quantity');
            $unitPrice = (float) $request->input('unit_price');
            $useTax = $request->has('use_tax') && $request->input('use_tax') !== null
                ? filter_var($request->input('use_tax'), FILTER_VALIDATE_BOOLEAN)
                : (bool) $hpsItem->use_tax;

            $amounts = ProcurementHpsItem::calculateAmounts($quantity, $unitPrice, $useTax);

            DB::beginTransaction();

            $hpsItem->update([
                'unit_id'    => (int) $request->input('unit_id'),
                'name'       => $request->input('name'),
                'quantity'   => $quantity,
                'unit_price' => $unitPrice,
                'use_tax'    => $useTax,
                'tax_rate'   => $amounts['tax_rate'],
                'tax_amount' => $amounts['tax_amount'],
                'subtotal'   => $amounts['subtotal'],
            ]);

            DB::commit();

            return $this->baseResponse(
                'Success update procurement hps item',
                $hpsItem->fresh(['unit'])->toApiArray()
            );
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id, $hpsId)
    {
        try {
            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            if ($procurement->isCancelled()) {
                return $this->errorValidateResponse('Cannot update cancelled procurement');
            }

            $hpsItem = $procurement->hpsItems()->find($hpsId);
            if (!$hpsItem) {
                return $this->invalidResponse('No data');
            }

            DB::beginTransaction();
            $hpsItem->delete();
            DB::commit();

            return $this->baseResponse('Success delete procurement hps item', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
