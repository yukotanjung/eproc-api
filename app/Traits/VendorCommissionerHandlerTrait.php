<?php

namespace App\Traits;

use App\Models\VendorCommissioner;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorCommissionerHandlerTrait
{
    private const COMMISSIONER_STORAGE_DIR = 'vendor/komisaris';

    protected function listCommissioners(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorCommissioner::getVendorCommissioner($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorCommissioner $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar komisaris', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createCommissioner(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $photoPath = $request->file('photo')->store(self::COMMISSIONER_STORAGE_DIR, 'local');
            $npwpPath = $request->file('npwp')->store(self::COMMISSIONER_STORAGE_DIR, 'local');
            $ktpPath = $request->file('ktp')->store(self::COMMISSIONER_STORAGE_DIR, 'local');

            DB::beginTransaction();

            $commissioner = VendorCommissioner::create([
                'vendor_id'   => $vendorId,
                'name'        => $request->name,
                'npwp_number' => $request->npwp_number,
                'position'    => $request->position,
                'photo_path'  => $photoPath,
                'npwp_path'   => $npwpPath,
                'ktp_path'    => $ktpPath,
            ]);

            DB::commit();

            return $this->baseResponse('Data komisaris berhasil dibuat', $commissioner->toApiArray());
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

    protected function getCommissionerDetail(int $vendorId, $commissionerId)
    {
        $commissioner = VendorCommissioner::where('vendor_id', $vendorId)->find($commissionerId);
        if (!$commissioner) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail komisaris', $commissioner->toApiArray());
    }

    protected function modifyCommissioner(Request $request, int $vendorId, $commissionerId)
    {
        try {
            $commissioner = VendorCommissioner::where('vendor_id', $vendorId)->find($commissionerId);
            if (!$commissioner) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'name'        => $request->name,
                'npwp_number' => $request->npwp_number,
                'position'    => $request->position,
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
                    $newPath = $request->file($inputKey)->store(self::COMMISSIONER_STORAGE_DIR, 'local');
                    $newPaths[] = $newPath;
                    if (!empty($commissioner->{$column})) {
                        $oldPaths[] = $commissioner->{$column};
                    }
                    $updates[$column] = $newPath;
                }
            }

            DB::beginTransaction();
            $commissioner->update($updates);
            DB::commit();

            foreach ($oldPaths as $oldPath) {
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            return $this->baseResponse('Data komisaris berhasil diperbarui', $commissioner->fresh()->toApiArray());
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

    protected function removeCommissioner(int $vendorId, $commissionerId)
    {
        try {
            $commissioner = VendorCommissioner::where('vendor_id', $vendorId)->find($commissionerId);
            if (!$commissioner) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $pathsToDelete = array_filter([
                $commissioner->photo_path,
                $commissioner->npwp_path,
                $commissioner->ktp_path,
            ]);

            DB::beginTransaction();
            $commissioner->delete();
            DB::commit();

            $disk = Storage::disk('local');
            foreach ($pathsToDelete as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            return $this->baseResponse('Data komisaris berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveCommissionerFile(int $vendorId, $commissionerId, string $type)
    {
        $columnMap = [
            'photo' => 'photo_path',
            'npwp'  => 'npwp_path',
            'ktp'   => 'ktp_path',
        ];

        if (!isset($columnMap[$type])) {
            return $this->invalidResponse('Tipe file tidak valid', 400);
        }

        $commissioner = VendorCommissioner::where('vendor_id', $vendorId)->find($commissionerId);
        if (!$commissioner) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $commissioner->{$columnMap[$type]};
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveCommissionerZip(int $vendorId, $commissionerId)
    {
        $commissioner = VendorCommissioner::where('vendor_id', $vendorId)->find($commissionerId);
        if (!$commissioner) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        $files = [
            'photo' => $commissioner->photo_path,
            'npwp'  => $commissioner->npwp_path,
            'ktp'   => $commissioner->ktp_path,
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

        $tempZip = tempnam(sys_get_temp_dir(), 'commissioner_zip_');
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

        $slugName = Str::slug($commissioner->name) ?: "commissioner-{$commissioner->id}";
        $downloadName = "komisaris-{$slugName}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
