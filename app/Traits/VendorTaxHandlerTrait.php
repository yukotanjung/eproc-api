<?php

namespace App\Traits;

use App\Models\VendorTax;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorTaxHandlerTrait
{
    private const TAX_STORAGE_DIR = 'vendor/pajak';

    protected function listTaxes(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorTax::getVendorTax($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorTax $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar data pajak', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createTax(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $receiptPath = $request->file('receipt_file')->store(self::TAX_STORAGE_DIR, 'local');

            DB::beginTransaction();

            $tax = VendorTax::create([
                'vendor_id'      => $vendorId,
                'report_date'    => $request->report_date,
                'tax_id'         => $request->tax_id,
                'receipt_number' => $request->receipt_number,
                'receipt_path'   => $receiptPath,
            ]);

            DB::commit();

            $tax->load('tax');
            return $this->baseResponse('Data pajak berhasil dibuat', $tax->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            if (!empty($receiptPath) && isset($disk) && $disk->exists($receiptPath)) {
                $disk->delete($receiptPath);
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getTaxDetail(int $vendorId, $taxId)
    {
        $tax = VendorTax::with('tax')->where('vendor_id', $vendorId)->find($taxId);
        if (!$tax) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail data pajak', $tax->toApiArray());
    }

    protected function modifyTax(Request $request, int $vendorId, $taxId)
    {
        try {
            $tax = VendorTax::where('vendor_id', $vendorId)->find($taxId);
            if (!$tax) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'report_date'    => $request->report_date,
                'tax_id'         => $request->tax_id,
                'receipt_number' => $request->receipt_number,
            ];

            $oldPath = null;
            $newPath = null;

            if ($request->hasFile('receipt_file')) {
                $newPath = $request->file('receipt_file')->store(self::TAX_STORAGE_DIR, 'local');
                $oldPath = $tax->receipt_path;
                $updates['receipt_path'] = $newPath;
            }

            DB::beginTransaction();
            $tax->update($updates);
            DB::commit();

            if ($oldPath && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }

            return $this->baseResponse('Data pajak berhasil diperbarui', $tax->fresh()->load('tax')->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            if ($newPath && isset($disk) && $disk->exists($newPath)) {
                $disk->delete($newPath);
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function removeTax(int $vendorId, $taxId)
    {
        try {
            $tax = VendorTax::where('vendor_id', $vendorId)->find($taxId);
            if (!$tax) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $receiptPath = $tax->receipt_path;

            DB::beginTransaction();
            $tax->delete();
            DB::commit();

            $disk = Storage::disk('local');
            if ($receiptPath && $disk->exists($receiptPath)) {
                $disk->delete($receiptPath);
            }

            return $this->baseResponse('Data pajak berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveTaxFile(int $vendorId, $taxId)
    {
        $tax = VendorTax::where('vendor_id', $vendorId)->find($taxId);
        if (!$tax) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $tax->receipt_path;
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveTaxZip(int $vendorId, $taxId)
    {
        $tax = VendorTax::where('vendor_id', $vendorId)->find($taxId);
        if (!$tax) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        if (empty($tax->receipt_path) || !$disk->exists($tax->receipt_path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        $tempZip = tempnam(sys_get_temp_dir(), 'tax_zip_');
        $zipPath = $tempZip . '.zip';
        @unlink($tempZip);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->invalidResponse('Gagal membuat file zip', 500);
        }

        $ext = pathinfo($tax->receipt_path, PATHINFO_EXTENSION);
        $entryName = $ext !== '' ? "bukti-pajak.{$ext}" : 'bukti-pajak';
        $zip->addFile($disk->path($tax->receipt_path), $entryName);
        $zip->close();

        $slugNo = Str::slug($tax->receipt_number) ?: "pajak-{$tax->id}";
        $downloadName = "pajak-{$slugNo}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
