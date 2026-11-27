<?php

namespace App\Traits;

use App\Models\VendorExperience;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorExperienceHandlerTrait
{
    private const EXPERIENCE_STORAGE_DIR = 'vendor/pengalaman';

    protected function listExperiences(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorExperience::getVendorExperience($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorExperience $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar pengalaman kerja', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createExperience(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $contractPath = $request->file('contract_file')->store(self::EXPERIENCE_STORAGE_DIR, 'local');
            $handoverPath = $request->file('handover_file')->store(self::EXPERIENCE_STORAGE_DIR, 'local');

            DB::beginTransaction();

            $experience = VendorExperience::create([
                'vendor_id'          => $vendorId,
                'job_name'           => $request->job_name,
                'contract_number'    => $request->contract_number,
                'contract_value'     => $request->contract_value,
                'job_type_id'        => $request->job_type_id,
                'client_name'        => $request->client_name,
                'location'           => $request->location,
                'address'            => $request->address,
                'contract_date'      => $request->contract_date,
                'contract_end_date'  => $request->contract_end_date,
                'contract_file_path' => $contractPath,
                'handover_date'      => $request->handover_date,
                'handover_number'    => $request->handover_number,
                'handover_file_path' => $handoverPath,
            ]);

            DB::commit();

            $experience->load('jobType');
            return $this->baseResponse('Data pengalaman kerja berhasil dibuat', $experience->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            foreach ([$contractPath ?? null, $handoverPath ?? null] as $path) {
                if ($path && isset($disk) && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getExperienceDetail(int $vendorId, $experienceId)
    {
        $experience = VendorExperience::with('jobType')->where('vendor_id', $vendorId)->find($experienceId);
        if (!$experience) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail pengalaman kerja', $experience->toApiArray());
    }

    protected function modifyExperience(Request $request, int $vendorId, $experienceId)
    {
        try {
            $experience = VendorExperience::where('vendor_id', $vendorId)->find($experienceId);
            if (!$experience) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'job_name'          => $request->job_name,
                'contract_number'   => $request->contract_number,
                'contract_value'    => $request->contract_value,
                'job_type_id'       => $request->job_type_id,
                'client_name'       => $request->client_name,
                'location'          => $request->location,
                'address'           => $request->address,
                'contract_date'     => $request->contract_date,
                'contract_end_date' => $request->contract_end_date,
                'handover_date'     => $request->handover_date,
                'handover_number'   => $request->handover_number,
            ];

            $fileFields = [
                'contract_file' => 'contract_file_path',
                'handover_file' => 'handover_file_path',
            ];

            $oldPaths = [];
            $newPaths = [];

            foreach ($fileFields as $inputKey => $column) {
                if ($request->hasFile($inputKey)) {
                    $newPath = $request->file($inputKey)->store(self::EXPERIENCE_STORAGE_DIR, 'local');
                    $newPaths[] = $newPath;
                    if (!empty($experience->{$column})) {
                        $oldPaths[] = $experience->{$column};
                    }
                    $updates[$column] = $newPath;
                }
            }

            DB::beginTransaction();
            $experience->update($updates);
            DB::commit();

            foreach ($oldPaths as $oldPath) {
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            return $this->baseResponse('Data pengalaman kerja berhasil diperbarui', $experience->fresh()->load('jobType')->toApiArray());
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

    protected function removeExperience(int $vendorId, $experienceId)
    {
        try {
            $experience = VendorExperience::where('vendor_id', $vendorId)->find($experienceId);
            if (!$experience) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $pathsToDelete = array_filter([
                $experience->contract_file_path,
                $experience->handover_file_path,
            ]);

            DB::beginTransaction();
            $experience->delete();
            DB::commit();

            $disk = Storage::disk('local');
            foreach ($pathsToDelete as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            return $this->baseResponse('Data pengalaman kerja berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveExperienceFile(int $vendorId, $experienceId, string $type)
    {
        $columnMap = [
            'contract' => 'contract_file_path',
            'handover' => 'handover_file_path',
        ];

        if (!isset($columnMap[$type])) {
            return $this->invalidResponse('Tipe file tidak valid', 400);
        }

        $experience = VendorExperience::where('vendor_id', $vendorId)->find($experienceId);
        if (!$experience) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $experience->{$columnMap[$type]};
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveExperienceZip(int $vendorId, $experienceId)
    {
        $experience = VendorExperience::where('vendor_id', $vendorId)->find($experienceId);
        if (!$experience) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        $files = [
            'kontrak'      => $experience->contract_file_path,
            'berita-acara' => $experience->handover_file_path,
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

        $tempZip = tempnam(sys_get_temp_dir(), 'experience_zip_');
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

        $slugNo = Str::slug($experience->contract_number) ?: "pengalaman-{$experience->id}";
        $downloadName = "pengalaman-{$slugNo}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
