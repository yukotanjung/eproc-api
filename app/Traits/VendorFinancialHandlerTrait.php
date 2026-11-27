<?php

namespace App\Traits;

use App\Models\VendorFinancial;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait VendorFinancialHandlerTrait
{
    protected function listFinancials(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorFinancial::getVendorFinancial($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorFinancial $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar data keuangan', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createFinancial(Request $request, int $vendorId)
    {
        try {
            DB::beginTransaction();

            $financial = VendorFinancial::create([
                'vendor_id'              => $vendorId,
                'report_date'            => $request->report_date,
                'fixed_assets'           => $request->fixed_assets,
                'current_assets'         => $request->current_assets,
                'other_assets'           => $request->other_assets,
                'long_term_liabilities'  => $request->long_term_liabilities,
                'short_term_liabilities' => $request->short_term_liabilities,
                'capital'                => $request->capital,
                'net_worth'              => $request->net_worth,
            ]);

            DB::commit();

            return $this->baseResponse('Data keuangan berhasil dibuat', $financial->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getFinancialDetail(int $vendorId, $financialId)
    {
        $financial = VendorFinancial::where('vendor_id', $vendorId)->find($financialId);
        if (!$financial) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail data keuangan', $financial->toApiArray());
    }

    protected function modifyFinancial(Request $request, int $vendorId, $financialId)
    {
        try {
            $financial = VendorFinancial::where('vendor_id', $vendorId)->find($financialId);
            if (!$financial) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            DB::beginTransaction();

            $financial->update([
                'report_date'            => $request->report_date,
                'fixed_assets'           => $request->fixed_assets,
                'current_assets'         => $request->current_assets,
                'other_assets'           => $request->other_assets,
                'long_term_liabilities'  => $request->long_term_liabilities,
                'short_term_liabilities' => $request->short_term_liabilities,
                'capital'                => $request->capital,
                'net_worth'              => $request->net_worth,
            ]);

            DB::commit();

            return $this->baseResponse('Data keuangan berhasil diperbarui', $financial->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function removeFinancial(int $vendorId, $financialId)
    {
        try {
            $financial = VendorFinancial::where('vendor_id', $vendorId)->find($financialId);
            if (!$financial) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            DB::beginTransaction();
            $financial->delete();
            DB::commit();

            return $this->baseResponse('Data keuangan berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
