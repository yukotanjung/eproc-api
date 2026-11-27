<?php

namespace App\Traits;

use App\Models\VendorOwner;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorOwnerHandlerTrait
{
    private const OWNER_STORAGE_DIR = 'vendor/pemilik';

    protected function listOwners(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorOwner::getVendorOwner($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorOwner $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar pemilik perusahaan', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createOwner(Request $request, int $vendorId)
    {
        try {
            $existingPercentage = (float) VendorOwner::where('vendor_id', $vendorId)->sum('percentage');
            if ($existingPercentage + (float) $request->percentage > 100) {
                return $this->errorResponses('Total prosentase kepemilikan tidak boleh melebihi 100%');
            }

            $disk = Storage::disk('local');
            $photoPath = $request->file('photo')->store(self::OWNER_STORAGE_DIR, 'local');
            $npwpPath = $request->file('npwp')->store(self::OWNER_STORAGE_DIR, 'local');
            $ktpPath = $request->file('ktp')->store(self::OWNER_STORAGE_DIR, 'local');

            DB::beginTransaction();

            $owner = VendorOwner::create([
                'vendor_id'   => $vendorId,
                'name'        => $request->name,
                'ktp_number'  => $request->ktp_number,
                'npwp_number' => $request->npwp_number,
                'address'     => $request->address,
                'percentage'  => $request->percentage,
                'photo_path'  => $photoPath,
                'npwp_path'   => $npwpPath,
                'ktp_path'    => $ktpPath,
            ]);

            DB::commit();

            return $this->baseResponse('Data pemilik perusahaan berhasil dibuat', $owner->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            foreach ([$photoPath ?? null, $npwpPath ?? null, $ktpPath ?? null] as $path) {
                if ($path && isset($disk) && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getOwnerDetail(int $vendorId, $ownerId)
    {
        $owner = VendorOwner::where('vendor_id', $vendorId)->find($ownerId);
        if (!$owner) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail pemilik perusahaan', $owner->toApiArray());
    }

    protected function modifyOwner(Request $request, int $vendorId, $ownerId)
    {
        try {
            $owner = VendorOwner::where('vendor_id', $vendorId)->find($ownerId);
            if (!$owner) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $otherPercentage = (float) VendorOwner::where('vendor_id', $vendorId)
                ->where('id', '<>', $owner->id)
                ->sum('percentage');
            if ($otherPercentage + (float) $request->percentage > 100) {
                return $this->errorResponses('Total prosentase kepemilikan tidak boleh melebihi 100%');
            }

            $disk = Storage::disk('local');
            $updates = [
                'name'        => $request->name,
                'ktp_number'  => $request->ktp_number,
                'npwp_number' => $request->npwp_number,
                'address'     => $request->address,
                'percentage'  => $request->percentage,
            ];

            $fileFields = [
                'photo' => 'photo_path',
                'npwp'  => 'npwp_path',
                'ktp'   => 'ktp_path',
            ];

            $oldPaths = [];
            $newPaths = [];

            foreach ($fileFields as $inputKey => $column) {
                if ($request->hasFile($inputKey)) {
                    $newPath = $request->file($inputKey)->store(self::OWNER_STORAGE_DIR, 'local');
                    $newPaths[] = $newPath;
                    if (!empty($owner->{$column})) {
                        $oldPaths[] = $owner->{$column};
                    }
                    $updates[$column] = $newPath;
                }
            }

            DB::beginTransaction();
            $owner->update($updates);
            DB::commit();

            foreach ($oldPaths as $oldPath) {
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            return $this->baseResponse('Data pemilik perusahaan berhasil diperbarui', $owner->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            foreach ($newPaths ?? [] as $newPath) {
                if (isset($disk) && $disk->exists($newPath)) {
                    $disk->delete($newPath);
                }
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function removeOwner(int $vendorId, $ownerId)
    {
        try {
            $owner = VendorOwner::where('vendor_id', $vendorId)->find($ownerId);
            if (!$owner) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $pathsToDelete = array_filter([
                $owner->photo_path,
                $owner->npwp_path,
                $owner->ktp_path,
            ]);

            DB::beginTransaction();
            $owner->delete();
            DB::commit();

            $disk = Storage::disk('local');
            foreach ($pathsToDelete as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            return $this->baseResponse('Data pemilik perusahaan berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveOwnerFile(int $vendorId, $ownerId, string $type)
    {
        $columnMap = [
            'photo' => 'photo_path',
            'npwp'  => 'npwp_path',
            'ktp'   => 'ktp_path',
        ];

        if (!isset($columnMap[$type])) {
            return $this->invalidResponse('Tipe file tidak valid', 400);
        }

        $owner = VendorOwner::where('vendor_id', $vendorId)->find($ownerId);
        if (!$owner) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $owner->{$columnMap[$type]};
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveOwnerZip(int $vendorId, $ownerId)
    {
        $owner = VendorOwner::where('vendor_id', $vendorId)->find($ownerId);
        if (!$owner) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        $files = [
            'photo' => $owner->photo_path,
            'npwp'  => $owner->npwp_path,
            'ktp'   => $owner->ktp_path,
        ];

        $available = [];
        foreach ($files as $label => $path) {
            if (!empty($path) && $disk->exists($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $entryName = $ext !== '' ? "{$label}.{$ext}" : $label;
                $available[$entryName] = $disk->path($path);
            }
        }

        if (empty($available)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        $tempZip = tempnam(sys_get_temp_dir(), 'owner_zip_');
        $zipPath = $tempZip . '.zip';
        @unlink($tempZip);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->invalidResponse('Gagal membuat file zip', 500);
        }

        foreach ($available as $entryName => $fullPath) {
            $zip->addFile($fullPath, $entryName);
        }
        $zip->close();

        $slugName = Str::slug($owner->name) ?: "owner-{$owner->id}";
        $downloadName = "pemilik-{$slugName}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
