<?php

namespace App\Traits;

use App\Models\VendorLegal;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

trait VendorLegalHandlerTrait
{
    private const LEGAL_STORAGE_DIR = 'vendor/hukum';

    protected function listLegals(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorLegal::getVendorLegal($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorLegal $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar data hukum', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createLegal(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $establishmentPath = $request->file('establishment_deed_file')->store(self::LEGAL_STORAGE_DIR, 'local');
            $amendmentPath = $request->hasFile('amendment_deed_file')
                ? $request->file('amendment_deed_file')->store(self::LEGAL_STORAGE_DIR, 'local')
                : null;

            DB::beginTransaction();

            $legal = VendorLegal::create([
                'vendor_id'                 => $vendorId,
                'establishment_date'        => $request->establishment_date,
                'establishment_deed_number' => $request->establishment_deed_number,
                'establishment_notary'      => $request->establishment_notary,
                'establishment_deed_path'   => $establishmentPath,
                'amendment_date'            => $request->amendment_date,
                'amendment_deed_number'     => $request->amendment_deed_number,
                'amendment_notary'          => $request->amendment_notary,
                'amendment_deed_path'       => $amendmentPath,
            ]);

            DB::commit();

            return $this->baseResponse('Data hukum berhasil dibuat', $legal->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            foreach ([$establishmentPath ?? null, $amendmentPath ?? null] as $path) {
                if ($path && isset($disk) && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getLegalDetail(int $vendorId, $legalId)
    {
        $legal = VendorLegal::where('vendor_id', $vendorId)->find($legalId);
        if (!$legal) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail data hukum', $legal->toApiArray());
    }

    protected function modifyLegal(Request $request, int $vendorId, $legalId)
    {
        try {
            $legal = VendorLegal::where('vendor_id', $vendorId)->find($legalId);
            if (!$legal) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'establishment_date'        => $request->establishment_date,
                'establishment_deed_number' => $request->establishment_deed_number,
                'establishment_notary'      => $request->establishment_notary,
                'amendment_date'            => $request->has('amendment_date') ? $request->amendment_date : $legal->amendment_date,
                'amendment_deed_number'     => $request->has('amendment_deed_number') ? $request->amendment_deed_number : $legal->amendment_deed_number,
                'amendment_notary'          => $request->has('amendment_notary') ? $request->amendment_notary : $legal->amendment_notary,
            ];

            $fileFields = [
                'establishment_deed_file' => 'establishment_deed_path',
                'amendment_deed_file'     => 'amendment_deed_path',
            ];

            $oldPaths = [];
            $newPaths = [];

            foreach ($fileFields as $inputKey => $column) {
                if ($request->hasFile($inputKey)) {
                    $newPath = $request->file($inputKey)->store(self::LEGAL_STORAGE_DIR, 'local');
                    $newPaths[] = $newPath;
                    if (!empty($legal->{$column})) {
                        $oldPaths[] = $legal->{$column};
                    }
                    $updates[$column] = $newPath;
                }
            }

            DB::beginTransaction();
            $legal->update($updates);
            DB::commit();

            foreach ($oldPaths as $oldPath) {
                if ($disk->exists($oldPath)) {
                    $disk->delete($oldPath);
                }
            }

            return $this->baseResponse('Data hukum berhasil diperbarui', $legal->fresh()->toApiArray());
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

    protected function removeLegal(int $vendorId, $legalId)
    {
        try {
            $legal = VendorLegal::where('vendor_id', $vendorId)->find($legalId);
            if (!$legal) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $pathsToDelete = array_filter([
                $legal->establishment_deed_path,
                $legal->amendment_deed_path,
            ]);

            DB::beginTransaction();
            $legal->delete();
            DB::commit();

            $disk = Storage::disk('local');
            foreach ($pathsToDelete as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            return $this->baseResponse('Data hukum berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveLegalFile(int $vendorId, $legalId, string $type)
    {
        $columnMap = [
            'establishment' => 'establishment_deed_path',
            'amendment'     => 'amendment_deed_path',
        ];

        if (!isset($columnMap[$type])) {
            return $this->invalidResponse('Tipe file tidak valid', 400);
        }

        $legal = VendorLegal::where('vendor_id', $vendorId)->find($legalId);
        if (!$legal) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $legal->{$columnMap[$type]};
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }

    protected function serveLegalZip(int $vendorId, $legalId)
    {
        $legal = VendorLegal::where('vendor_id', $vendorId)->find($legalId);
        if (!$legal) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $disk = Storage::disk('local');
        $files = [
            'akta-pendirian' => $legal->establishment_deed_path,
            'akta-perubahan' => $legal->amendment_deed_path,
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

        $tempZip = tempnam(sys_get_temp_dir(), 'legal_zip_');
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

        $slugNo = Str::slug($legal->establishment_deed_number) ?: "hukum-{$legal->id}";
        $downloadName = "hukum-{$slugNo}.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }
}
