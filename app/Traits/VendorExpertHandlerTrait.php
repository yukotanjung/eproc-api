<?php

namespace App\Traits;

use App\Models\VendorExpert;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorExpertHandlerTrait
{
    private const EXPERT_STORAGE_DIR = 'vendor/tenaga-ahli';

    protected function listExperts(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorExpert::getVendorExpert($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorExpert $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar tenaga ahli', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createExpert(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $photoPath = $request->file('photo')->store(self::EXPERT_STORAGE_DIR, 'local');
            $diplomaPath = $request->file('diploma')->store(self::EXPERT_STORAGE_DIR, 'local');
            $certificatePath = $request->hasFile('certificate')
                ? $request->file('certificate')->store(self::EXPERT_STORAGE_DIR, 'local')
                : null;

            DB::beginTransaction();

            $expert = VendorExpert::create([
                'vendor_id'          => $vendorId,
                'name'               => $request->name,
                'birth_place'        => $request->birth_place,
                'birth_date'         => $request->birth_date,
                'position'           => $request->position,
                'npwp_number'        => $request->npwp_number,
                'ktp_number'         => $request->ktp_number,
                'address'            => $request->address,
                'education_level_id' => $request->education_level_id,
                'major'              => $request->major,
                'diploma_number'     => $request->diploma_number,
                'experience_years'   => $request->experience_years,
                'photo_path'         => $photoPath,
                'diploma_path'       => $diplomaPath,
                'certificate_path'   => $certificatePath,
            ]);

            DB::commit();

            $expert->load('educationLevel');
            return $this->baseResponse('Data tenaga ahli berhasil dibuat', $expert->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            foreach ([$photoPath ?? null, $diplomaPath ?? null, $certificatePath ?? null] as $path) {
                if ($path && isset($disk) && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getExpertDetail(int $vendorId, $expertId)
    {
        $expert = VendorExpert::with('educationLevel')->where('vendor_id', $vendorId)->find($expertId);
        if (!$expert) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail tenaga ahli', $expert->toApiArray());
    }

    protected function modifyExpert(Request $request, int $vendorId, $expertId)
    {
        try {
            $expert = VendorExpert::where('vendor_id', $vendorId)->find($expertId);
            if (!$expert) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'name'               => $request->name,
                'birth_place'        => $request->birth_place,
                'birth_date'         => $request->birth_date,
                'position'           => $request->position,
                'npwp_number'        => $request->npwp_number,
                'ktp_number'         => $request->ktp_number,
                'address'            => $request->address,
                'education_level_id' => $request->education_level_id,
                'major'              => $request->major,
                'diploma_number'     => $request->diploma_number,
                'experience_years'   => $request->experience_years,
            ];

            $fileFields = [
                'photo'       => 'photo_path',
                'diploma'     => 'diploma_path',
                'certificate' => 'certificate_path',
            ];

            $oldPaths = [];
            $newPaths = [];

            foreach ($fileFields as $inputKey => $column) {
                if ($request->hasFile($inputKey)) {
                    $newPath = $request->file($inputKey)->store(self::EXPERT_STORAGE_DIR, 'local');
                    $newPaths[] = $newPath;
                    if (!empty($expert->{$column})) {
                        $oldPaths[] = $expert->{$column};
                    }
                    $updates[$column] = $newPath;
                }
            }

            DB::beginTransaction();
            $expert->update($updates);
            DB::commit();

            foreach ($oldPaths as $oldPath) {
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            return $this->baseResponse('Data tenaga ahli berhasil diperbarui', $expert->fresh()->load('educationLevel')->toApiArray());
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

    protected function removeExpert(int $vendorId, $expertId)
    {
        try {
            $expert = VendorExpert::where('vendor_id', $vendorId)->find($expertId);
            if (!$expert) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $pathsToDelete = array_filter([
                $expert->photo_path,
                $expert->diploma_path,
                $expert->certificate_path,
            ]);

            DB::beginTransaction();
            $expert->delete();
            DB::commit();

            $disk = Storage::disk('local');
            foreach ($pathsToDelete as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            return $this->baseResponse('Data tenaga ahli berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveExpertFile(int $vendorId, $expertId, string $type)
    {
        $columnMap = [
            'photo'       => 'photo_path',
            'diploma'     => 'diploma_path',
            'certificate' => 'certificate_path',
        ];

        if (!isset($columnMap[$type])) {
            return $this->invalidResponse('Tipe file tidak valid', 400);
        }

        $expert = VendorExpert::where('vendor_id', $vendorId)->find($expertId);
        if (!$expert) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $expert->{$columnMap[$type]};
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveExpertZip(int $vendorId, $expertId)
    {
        $expert = VendorExpert::where('vendor_id', $vendorId)->find($expertId);
        if (!$expert) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        $files = [
            'photo'       => $expert->photo_path,
            'diploma'     => $expert->diploma_path,
            'certificate' => $expert->certificate_path,
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

        $tempZip = tempnam(sys_get_temp_dir(), 'expert_zip_');
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

        $slugName = Str::slug($expert->name) ?: "expert-{$expert->id}";
        $downloadName = "tenaga-ahli-{$slugName}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
