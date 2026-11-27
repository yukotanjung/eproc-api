<?php

namespace App\Traits;

use App\Models\VendorLicense;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorLicenseHandlerTrait
{
    private const LICENSE_STORAGE_DIR = 'vendor/perizinan';

    protected function listLicenses(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorLicense::getVendorLicense($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorLicense $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar data perizinan', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createLicense(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $filePath = $request->file('file')->store(self::LICENSE_STORAGE_DIR, 'local');

            DB::beginTransaction();

            $license = VendorLicense::create([
                'vendor_id'      => $vendorId,
                'license_type'   => $request->license_type,
                'license_number' => $request->license_number,
                'issued_date'    => $request->issued_date,
                'expired_date'   => $request->expired_date,
                'issuing_agency' => $request->issuing_agency,
                'file_path'      => $filePath,
            ]);

            DB::commit();

            return $this->baseResponse('Data perizinan berhasil dibuat', $license->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            if (!empty($filePath) && isset($disk) && $disk->exists($filePath)) {
                $disk->delete($filePath);
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getLicenseDetail(int $vendorId, $licenseId)
    {
        $license = VendorLicense::where('vendor_id', $vendorId)->find($licenseId);
        if (!$license) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail data perizinan', $license->toApiArray());
    }

    protected function modifyLicense(Request $request, int $vendorId, $licenseId)
    {
        try {
            $license = VendorLicense::where('vendor_id', $vendorId)->find($licenseId);
            if (!$license) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'license_type'   => $request->license_type,
                'license_number' => $request->license_number,
                'issued_date'    => $request->issued_date,
                'expired_date'   => $request->expired_date,
                'issuing_agency' => $request->issuing_agency,
            ];

            $oldPath = null;
            $newPath = null;

            if ($request->hasFile('file')) {
                $newPath = $request->file('file')->store(self::LICENSE_STORAGE_DIR, 'local');
                $oldPath = $license->file_path;
                $updates['file_path'] = $newPath;
            }

            DB::beginTransaction();
            $license->update($updates);
            DB::commit();

            if ($oldPath && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }

            return $this->baseResponse('Data perizinan berhasil diperbarui', $license->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            if ($newPath && isset($disk) && $disk->exists($newPath)) {
                $disk->delete($newPath);
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function removeLicense(int $vendorId, $licenseId)
    {
        try {
            $license = VendorLicense::where('vendor_id', $vendorId)->find($licenseId);
            if (!$license) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $filePath = $license->file_path;

            DB::beginTransaction();
            $license->delete();
            DB::commit();

            $disk = Storage::disk('local');
            if ($filePath && $disk->exists($filePath)) {
                $disk->delete($filePath);
            }

            return $this->baseResponse('Data perizinan berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveLicenseFile(int $vendorId, $licenseId)
    {
        $license = VendorLicense::where('vendor_id', $vendorId)->find($licenseId);
        if (!$license) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $license->file_path;
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveLicenseZip(int $vendorId, $licenseId)
    {
        $license = VendorLicense::where('vendor_id', $vendorId)->find($licenseId);
        if (!$license) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        if (empty($license->file_path) || !$disk->exists($license->file_path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        $tempZip = tempnam(sys_get_temp_dir(), 'license_zip_');
        $zipPath = $tempZip . '.zip';
        @unlink($tempZip);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->invalidResponse('Gagal membuat file zip', 500);
        }

        $ext = pathinfo($license->file_path, PATHINFO_EXTENSION);
        $baseLabel = Str::slug($license->license_type) ?: 'perizinan';
        $entryName = $ext !== '' ? "{$baseLabel}.{$ext}" : $baseLabel;
        $zip->addFile($disk->path($license->file_path), $entryName);
        $zip->close();

        $slugNo = Str::slug($license->license_number) ?: "perizinan-{$license->id}";
        $downloadName = "perizinan-{$slugNo}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
