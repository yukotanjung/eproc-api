<?php

namespace Tests\Feature;

use App\Models\FundingSource;
use App\Models\JobType;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMethod;
use App\Models\Qualification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProcurementDocumentTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default', 'local'));
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Procurement::query()->forceDelete();
    }

    private function createProcurement(string $suffix = '001'): Procurement
    {
        $jobType = JobType::create(['name' => 'Konstruksi Doc ' . $suffix, 'is_active' => true]);
        $qualification = Qualification::create(['name' => 'Kecil Doc ' . $suffix, 'is_active' => true]);
        $fundingSource = FundingSource::create(['name' => 'APBN Doc ' . $suffix, 'is_active' => true]);
        $method = ProcurementMethod::create(['name' => 'Tender Doc ' . $suffix, 'is_active' => true]);

        $procurementId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement', [
                'job_number'            => 'PROC-DOC-' . $suffix,
                'name'                  => 'Paket Uji Dokumen ' . $suffix,
                'budget_ceiling'        => 500000000,
                'estimated_price'       => 450000000,
                'planned_date'          => '2026-03-10',
                'job_type_id'           => $jobType->id,
                'qualification_id'      => $qualification->id,
                'funding_source_id'     => $fundingSource->id,
                'budget_year'           => 2026,
                'project_location'      => 'Bandung',
                'procurement_method_id' => $method->id,
                'announcement_location' => 'Portal E-Proc',
            ])
            ->assertStatus(201)
            ->json('result.id');

        return Procurement::findOrFail($procurementId);
    }

    public function test_can_upload_various_formats_and_list_documents(): void
    {
        $procurement = $this->createProcurement('UP-1');
        $disk = Storage::disk(config('filesystems.default', 'local'));

        $pdfResponse = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name' => 'Dokumen RKS',
                'file' => UploadedFile::fake()->createWithContent('rks-2026.pdf', '%PDF-1.4 sample content'),
            ])
            ->assertStatus(201);

        $pdfDoc = $pdfResponse->json('result');
        $this->assertSame('Dokumen RKS', $pdfDoc['name']);
        $this->assertSame('rks-2026.pdf', $pdfDoc['file_name']);
        $this->assertSame('pdf', $pdfDoc['extension']);
        $this->assertTrue($pdfDoc['is_public']);

        $dwgResponse = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name'      => 'Gambar Teknis Gedung',
                'file'      => UploadedFile::fake()->createWithContent('denah-lantai-1.dwg', 'AC1027 CAD DATA'),
                'is_public' => 'false',
            ])
            ->assertStatus(201);

        $dwgDoc = $dwgResponse->json('result');
        $this->assertSame('Gambar Teknis Gedung', $dwgDoc['name']);
        $this->assertSame('dwg', $dwgDoc['extension']);
        $this->assertFalse($dwgDoc['is_public']);

        $xlsxResponse = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name'      => 'BOQ Kosong',
                'file'      => UploadedFile::fake()->createWithContent('boq.xlsx', 'XLSX-BINARY'),
                'is_public' => true,
            ])
            ->assertStatus(201);

        $xlsxDoc = $xlsxResponse->json('result');
        $this->assertSame('xlsx', $xlsxDoc['extension']);
        $this->assertTrue($xlsxDoc['is_public']);

        $storedModels = ProcurementDocument::where('procurement_id', $procurement->id)->get();
        $this->assertCount(3, $storedModels);
        foreach ($storedModels as $model) {
            $disk->assertExists($model->file_path);
        }

        $listResponse = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/document")
            ->assertStatus(201);

        $this->assertCount(3, $listResponse->json('result'));
    }

    public function test_can_update_metadata_replace_file_and_delete_document(): void
    {
        $procurement = $this->createProcurement('UPD-1');
        $disk = Storage::disk(config('filesystems.default', 'local'));

        $created = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name'      => 'KAK Awal',
                'file'      => UploadedFile::fake()->createWithContent('kak-v1.pdf', 'v1 content'),
                'is_public' => true,
            ])
            ->assertStatus(201)
            ->json('result');

        $docModel = ProcurementDocument::findOrFail($created['id']);
        $oldFilePath = $docModel->file_path;
        $disk->assertExists($oldFilePath);

        $updatedMeta = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document/{$docModel->id}", [
                'name'      => 'KAK Revisi Nama',
                'is_public' => false,
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertSame('KAK Revisi Nama', $updatedMeta['name']);
        $this->assertFalse($updatedMeta['is_public']);
        $disk->assertExists($oldFilePath);

        $updatedWithFile = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document/{$docModel->id}", [
                'name'      => 'KAK Revisi File Baru',
                'file'      => UploadedFile::fake()->createWithContent('kak-v2.docx', 'v2 docx content'),
                'is_public' => true,
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertSame('KAK Revisi File Baru', $updatedWithFile['name']);
        $this->assertSame('kak-v2.docx', $updatedWithFile['file_name']);
        $this->assertSame('docx', $updatedWithFile['extension']);
        $this->assertTrue($updatedWithFile['is_public']);

        $newFilePath = $docModel->fresh()->file_path;
        $disk->assertMissing($oldFilePath);
        $disk->assertExists($newFilePath);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}/document/{$docModel->id}")
            ->assertStatus(201);

        $disk->assertMissing($newFilePath);
        $this->assertSoftDeleted('procurement_documents', ['id' => $docModel->id]);
    }

    public function test_can_download_single_document_and_download_all_as_zip_with_deduplicated_names(): void
    {
        $procurement = $this->createProcurement('DL-1');

        $doc1 = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name'      => 'Lampiran Teknis',
                'file'      => UploadedFile::fake()->createWithContent('tech-1.pdf', 'CONTENT-PDF-1'),
                'is_public' => true,
            ])
            ->assertStatus(201)
            ->json('result');

        $doc2 = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name'      => 'Lampiran Teknis',
                'file'      => UploadedFile::fake()->createWithContent('tech-2.pdf', 'CONTENT-PDF-2'),
                'is_public' => false,
            ])
            ->assertStatus(201)
            ->json('result');

        $singleResponse = $this->actingAsApiUser()
            ->get("/api/procurement/{$procurement->id}/document/{$doc1['id']}/download")
            ->assertStatus(200);

        $this->assertStringContainsString('Lampiran Teknis.pdf', (string) $singleResponse->headers->get('content-disposition'));
        $this->assertSame('CONTENT-PDF-1', $singleResponse->streamedContent());

        $zipResponse = $this->actingAsApiUser()
            ->get("/api/procurement/{$procurement->id}/document/download-all")
            ->assertStatus(200);

        $this->assertSame('application/zip', $zipResponse->headers->get('content-type'));
        $zipFile = $zipResponse->baseResponse->getFile()->getPathname();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipFile) === true);
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('CONTENT-PDF-1', $zip->getFromName('Lampiran Teknis.pdf'));
        $this->assertSame('CONTENT-PDF-2', $zip->getFromName('Lampiran Teknis (1).pdf'));
        $zip->close();
    }

    public function test_public_endpoints_expose_and_bundle_only_public_documents(): void
    {
        $procurement = $this->createProcurement('PUB-1');

        $publicDoc = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name'      => 'Dokumen Umum',
                'file'      => UploadedFile::fake()->createWithContent('umum.pdf', 'PUBLIC-CONTENT'),
                'is_public' => true,
            ])
            ->assertStatus(201)
            ->json('result');

        $privateDoc = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name'      => 'HPS Rahasia',
                'file'      => UploadedFile::fake()->createWithContent('hps.xlsx', 'SECRET-CONTENT'),
                'is_public' => false,
            ])
            ->assertStatus(201)
            ->json('result');

        $publicList = $this->getJson("/api/public/procurement/{$procurement->id}/document")
            ->assertStatus(201)
            ->json('result');

        $this->assertCount(1, $publicList['documents']);
        $this->assertSame($publicDoc['id'], $publicList['documents'][0]['id']);

        $this->get("/api/public/procurement/{$procurement->id}/document/{$publicDoc['id']}/download")
            ->assertStatus(200);

        $this->getJson("/api/public/procurement/{$procurement->id}/document/{$privateDoc['id']}/download")
            ->assertStatus(403);

        $zipResponse = $this->get("/api/public/procurement/{$procurement->id}/document/download-all")
            ->assertStatus(200);

        $zipFile = $zipResponse->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipFile) === true);
        $this->assertSame(1, $zip->numFiles);
        $this->assertSame('PUBLIC-CONTENT', $zip->getFromName('Dokumen Umum.pdf'));
        $this->assertFalse($zip->getFromName('HPS Rahasia.xlsx'));
        $zip->close();
    }

    public function test_validation_rejects_disallowed_extensions_oversized_files_and_cancelled_procurement(): void
    {
        $procurement = $this->createProcurement('VAL-DOC-1');

        $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name' => 'Script Berbahaya',
                'file' => UploadedFile::fake()->create('exploit.exe', 100),
            ])
            ->assertStatus(400);

        $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name' => 'File Terlalu Besar',
                'file' => UploadedFile::fake()->create('huge.pdf', 21000),
            ])
            ->assertStatus(400);

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/document/download-all")
            ->assertStatus(400);

        $doc = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name' => 'Dokumen Valid',
                'file' => UploadedFile::fake()->createWithContent('valid.pdf', 'OK'),
            ])
            ->assertStatus(201)
            ->json('result');

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/cancel", [
                'reason' => 'Batal lelang',
            ])
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name' => 'Dokumen Baru Setelah Batal',
                'file' => UploadedFile::fake()->createWithContent('after-cancel.pdf', 'NO'),
            ])
            ->assertStatus(400);

        $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document/{$doc['id']}", [
                'name' => 'Ubah Setelah Batal',
            ])
            ->assertStatus(400);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}/document/{$doc['id']}")
            ->assertStatus(400);

        $this->getJson("/api/public/procurement/{$procurement->id}/document")
            ->assertStatus(403);
    }

    public function test_permissions_and_delete_procurement_cleanup(): void
    {
        $procurement = $this->createProcurement('PERM-DOC-1');
        $disk = Storage::disk(config('filesystems.default', 'local'));

        $doc = $this->actingAsApiUser()
            ->post("/api/procurement/{$procurement->id}/document", [
                'name' => 'Dokumen Uji Permission',
                'file' => UploadedFile::fake()->createWithContent('perm.pdf', 'PERM'),
            ])
            ->assertStatus(201)
            ->json('result');

        $docModel = ProcurementDocument::findOrFail($doc['id']);
        $filePath = $docModel->file_path;

        $readPerm = Permission::where('subject', 'procurement_document')->where('action', 'read')->firstOrFail();
        $role = Role::create(['name' => 'doc_reader_test', 'description' => 'Reader Doc']);
        $role->permissions()->sync([$readPerm->id]);

        $reader = User::create([
            'name'     => 'Reader Dokumen',
            'username' => 'reader_doc_user',
            'email'    => 'reader_doc@example.com',
            'password' => bcrypt('password'),
        ]);
        $reader->roles()->sync([$role->id]);

        $token = auth('api')->login($reader);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->withHeaders($headers)
            ->getJson("/api/procurement/{$procurement->id}/document")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->withHeaders($headers)
            ->get("/api/procurement/{$procurement->id}/document/{$doc['id']}/download")
            ->assertStatus(200);

        $this->withHeaders($headers)
            ->post("/api/procurement/{$procurement->id}/document", [
                'name' => 'Forbidden Create',
                'file' => UploadedFile::fake()->createWithContent('forbid.pdf', 'NO'),
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->post("/api/procurement/{$procurement->id}/document/{$doc['id']}", [
                'name' => 'Forbidden Update',
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson("/api/procurement/{$procurement->id}/document/{$doc['id']}")
            ->assertStatus(403);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}")
            ->assertStatus(201);

        $disk->assertMissing($filePath);
        $this->assertSoftDeleted('procurement_documents', ['id' => $doc['id']]);
    }
}
