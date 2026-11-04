<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ProcurementDocumentController extends Controller
{
    use RuleTrait;

    public function index($id)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $documents = $procurement->documents()
            ->orderBy('id')
            ->get()
            ->map(fn(ProcurementDocument $doc) => $doc->toApiArray())
            ->values()
            ->all();

        return $this->baseResponse('Success get procurement documents', $documents);
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

            $file = $request->file('file');
            $diskName = $this->diskName();
            $filePath = $file->store("procurement/{$procurement->id}/documents", $diskName);

            $isPublic = $request->has('is_public')
                ? filter_var($request->input('is_public'), FILTER_VALIDATE_BOOLEAN)
                : true;

            DB::beginTransaction();

            $document = ProcurementDocument::create([
                'procurement_id' => $procurement->id,
                'name'           => $request->input('name'),
                'file_path'      => $filePath,
                'file_name'      => $file->getClientOriginalName(),
                'mime_type'      => $file->getClientMimeType(),
                'file_size'      => $file->getSize(),
                'extension'      => strtolower($file->getClientOriginalExtension()),
                'is_public'      => $isPublic,
            ]);

            DB::commit();

            return $this->baseResponse('Success create procurement document', $document->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function update(Request $request, $id, $documentId)
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

            $document = $procurement->documents()->find($documentId);
            if (!$document) {
                return $this->invalidResponse('No data');
            }

            $updates = [
                'name' => $request->input('name'),
            ];

            if ($request->has('is_public')) {
                $updates['is_public'] = filter_var($request->input('is_public'), FILTER_VALIDATE_BOOLEAN);
            }

            $diskName = $this->diskName();
            $disk = Storage::disk($diskName);
            $oldFilePath = null;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $newFilePath = $file->store("procurement/{$procurement->id}/documents", $diskName);
                $oldFilePath = $document->file_path;

                $updates['file_path'] = $newFilePath;
                $updates['file_name'] = $file->getClientOriginalName();
                $updates['mime_type'] = $file->getClientMimeType();
                $updates['file_size'] = $file->getSize();
                $updates['extension'] = strtolower($file->getClientOriginalExtension());
            }

            DB::beginTransaction();
            $document->update($updates);
            DB::commit();

            if ($oldFilePath && $disk->exists($oldFilePath)) {
                $disk->delete($oldFilePath);
            }

            return $this->baseResponse('Success update procurement document', $document->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id, $documentId)
    {
        try {
            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            if ($procurement->isCancelled()) {
                return $this->errorValidateResponse('Cannot update cancelled procurement');
            }

            $document = $procurement->documents()->find($documentId);
            if (!$document) {
                return $this->invalidResponse('No data');
            }

            $filePath = $document->file_path;
            $disk = Storage::disk($this->diskName());

            DB::beginTransaction();
            $document->delete();
            DB::commit();

            if ($filePath && $disk->exists($filePath)) {
                $disk->delete($filePath);
            }

            return $this->baseResponse('Success delete procurement document', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function download($id, $documentId)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $document = $procurement->documents()->find($documentId);
        if (!$document) {
            return $this->invalidResponse('No data');
        }

        return $this->streamDocumentDownload($document);
    }

    public function downloadAll($id)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $documents = $procurement->documents()->orderBy('id')->get();
        if ($documents->isEmpty()) {
            return $this->errorValidateResponse('No documents available to download');
        }

        return $this->streamZipDownload($procurement, $documents);
    }

    public function publicIndex($id)
    {
        $procurement = $this->findActiveProcurement($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $documents = $procurement->documents()
            ->where('is_public', true)
            ->orderBy('id')
            ->get()
            ->map(fn(ProcurementDocument $doc) => $doc->toPublicApiArray())
            ->values()
            ->all();

        return $this->baseResponse('Success get public procurement documents', [
            'procurement' => [
                'id'         => $procurement->id,
                'job_number' => $procurement->job_number,
                'name'       => $procurement->name,
                'status'     => $procurement->status,
            ],
            'documents'       => $documents,
            'download_all_url' => url("/api/public/procurement/{$procurement->id}/document/download-all"),
        ]);
    }

    public function publicDownload($id, $documentId)
    {
        $procurement = $this->findActiveProcurement($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $document = $procurement->documents()
            ->where('is_public', true)
            ->find($documentId);

        if (!$document) {
            return $this->invalidResponse('No data');
        }

        return $this->streamDocumentDownload($document);
    }

    public function publicDownloadAll($id)
    {
        $procurement = $this->findActiveProcurement($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $documents = $procurement->documents()
            ->where('is_public', true)
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            return $this->errorValidateResponse('No documents available to download');
        }

        return $this->streamZipDownload($procurement, $documents);
    }

    private function findActiveProcurement($id): ?Procurement
    {
        return Procurement::where('is_active', true)
            ->where('status', '<>', Procurement::STATUS_CANCELLED)
            ->find($id);
    }

    private function diskName(): string
    {
        return config('filesystems.default', 'local');
    }

    private function streamDocumentDownload(ProcurementDocument $document)
    {
        $disk = Storage::disk($this->diskName());

        if (!$document->file_path || !$disk->exists($document->file_path)) {
            return $this->invalidResponse('File not found');
        }

        $downloadName = $this->buildDownloadFilename($document->name, $document->extension);
        $headers = [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ];

        return $disk->download($document->file_path, $downloadName, $headers);
    }

    private function streamZipDownload(Procurement $procurement, Collection $documents)
    {
        $disk = Storage::disk($this->diskName());
        $tempBase = tempnam(sys_get_temp_dir(), 'proc_doc_');
        $zipPath = $tempBase . '.zip';
        @unlink($tempBase);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->invalidResponse('Failed to create zip archive');
        }

        $usedNames = [];
        $addedCount = 0;

        foreach ($documents as $document) {
            if (!$document->file_path || !$disk->exists($document->file_path)) {
                continue;
            }

            $entryName = $this->resolveUniqueZipEntryName($document->name, $document->extension, $usedNames);
            $zip->addFromString($entryName, $disk->get($document->file_path));
            $addedCount++;
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($zipPath);
            return $this->invalidResponse('File not found');
        }

        $slug = Str::slug($procurement->job_number ?: ('procurement-' . $procurement->id));
        $zipFileName = "procurement-{$slug}-documents.zip";

        return response()
            ->download($zipPath, $zipFileName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function buildDownloadFilename(string $name, ?string $extension): string
    {
        $cleanBase = $this->sanitizeBaseName($name, $extension);
        $ext = ltrim((string) $extension, '.');

        return $ext !== '' ? "{$cleanBase}.{$ext}" : $cleanBase;
    }

    private function resolveUniqueZipEntryName(string $name, ?string $extension, array &$usedNames): string
    {
        $cleanBase = $this->sanitizeBaseName($name, $extension);
        $ext = ltrim((string) $extension, '.');
        $extSuffix = $ext !== '' ? ".{$ext}" : '';

        $candidate = "{$cleanBase}{$extSuffix}";
        $lowerKey = strtolower($candidate);

        if (!isset($usedNames[$lowerKey])) {
            $usedNames[$lowerKey] = 0;
            return $candidate;
        }

        $counter = $usedNames[$lowerKey] + 1;
        while (true) {
            $numbered = "{$cleanBase} ({$counter}){$extSuffix}";
            $numberedKey = strtolower($numbered);
            if (!isset($usedNames[$numberedKey])) {
                $usedNames[$lowerKey] = $counter;
                $usedNames[$numberedKey] = 0;
                return $numbered;
            }
            $counter++;
        }
    }

    private function sanitizeBaseName(string $name, ?string $extension): string
    {
        $clean = trim(preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/', '-', $name) ?? '');
        if ($clean === '') {
            $clean = 'document';
        }

        $ext = ltrim((string) $extension, '.');
        if ($ext !== '' && Str::endsWith(strtolower($clean), '.' . strtolower($ext))) {
            $clean = substr($clean, 0, -(strlen($ext) + 1));
            $clean = trim($clean) !== '' ? trim($clean) : 'document';
        }

        return $clean;
    }
}
