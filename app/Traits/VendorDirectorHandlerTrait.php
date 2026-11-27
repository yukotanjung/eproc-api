<?php

namespace App\Traits;

use App\Models\VendorDirector;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorDirectorHandlerTrait
{
    private const DIRECTOR_STORAGE_DIR = 'vendor/direksi';

    protected function listDirectors(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorDirector::getVendorDirector($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorDirector $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar direksi', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createDirector(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $photoPath = $request->file('photo')->store(self::DIRECTOR_STORAGE_DIR, 'local');
            $npwpPath = $request->file('npwp')->store(self::DIRECTOR_STORAGE_DIR, 'local');
            $ktpPath = $request->file('ktp')->store(self::DIRECTOR_STORAGE_DIR, 'local');

            DB::beginTransaction();

            $director = VendorDirector::create([
                'vendor_id'   => $vendorId,
                'name'        => $request->name,
                'ktp_number'  => $request->ktp_number,
                'npwp_number' => $request->npwp_number,
                'position'    => $request->position,
                'is_pic'      => $request->has('is_pic') ? filter_var($request->is_pic, FILTER_VALIDATE_BOOLEAN) : false,
                'photo_path'  => $photoPath,
                'npwp_path'   => $npwpPath,
                'ktp_path'    => $ktpPath,
            ]);

            DB::commit();

            return $this->baseResponse('Data direksi berhasil dibuat', $director->toApiArray());
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

    protected function getDirectorDetail(int $vendorId, $directorId)
    {
        $director = VendorDirector::where('vendor_id', $vendorId)->find($directorId);
        if (!$director) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail direksi', $director->toApiArray());
    }

    protected function modifyDirector(Request $request, int $vendorId, $directorId)
    {
        try {
            $director = VendorDirector::where('vendor_id', $vendorId)->find($directorId);
            if (!$director) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'name'        => $request->name,
                'ktp_number'  => $request->ktp_number,
                'npwp_number' => $request->npwp_number,
                'position'    => $request->position,
                'is_pic'      => $request->has('is_pic') ? filter_var($request->is_pic, FILTER_VALIDATE_BOOLEAN) : $director->is_pic,
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
                    $newPath = $request->file($inputKey)->store(self::DIRECTOR_STORAGE_DIR, 'local');
                    $newPaths[] = $newPath;
                    if (!empty($director->{$column})) {
                        $oldPaths[] = $director->{$column};
                    }
                    $updates[$column] = $newPath;
                }
            }

            DB::beginTransaction();
            $director->update($updates);
            DB::commit();

            foreach ($oldPaths as $oldPath) {
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            return $this->baseResponse('Data direksi berhasil diperbarui', $director->fresh()->toApiArray());
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

    protected function removeDirector(int $vendorId, $directorId)
    {
        try {
            $director = VendorDirector::where('vendor_id', $vendorId)->find($directorId);
            if (!$director) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $pathsToDelete = array_filter([
                $director->photo_path,
                $director->npwp_path,
                $director->ktp_path,
            ]);

            DB::beginTransaction();
            $director->delete();
            DB::commit();

            $disk = Storage::disk('local');
            foreach ($pathsToDelete as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            return $this->baseResponse('Data direksi berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveDirectorFile(int $vendorId, $directorId, string $type)
    {
        $columnMap = [
            'photo' => 'photo_path',
            'npwp'  => 'npwp_path',
            'ktp'   => 'ktp_path',
        ];

        if (!isset($columnMap[$type])) {
            return $this->invalidResponse('Tipe file tidak valid', 400);
        }

        $director = VendorDirector::where('vendor_id', $vendorId)->find($directorId);
        if (!$director) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $director->{$columnMap[$type]};
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveDirectorZip(int $vendorId, $directorId)
    {
        $director = VendorDirector::where('vendor_id', $vendorId)->find($directorId);
        if (!$director) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        $files = [
            'photo' => $director->photo_path,
            'npwp'  => $director->npwp_path,
            'ktp'   => $director->ktp_path,
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

        $tempZip = tempnam(sys_get_temp_dir(), 'director_zip_');
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

        $slugName = Str::slug($director->name) ?: "director-{$director->id}";
        $downloadName = "direksi-{$slugName}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
