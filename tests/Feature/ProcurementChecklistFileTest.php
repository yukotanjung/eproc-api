<?php

namespace Tests\Feature;

use App\Models\FundingSource;
use App\Models\JobType;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\ProcurementChecklistFile;
use App\Models\ProcurementMethod;
use App\Models\ProcurementMethodStage;
use App\Models\Qualification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

class ProcurementChecklistFileTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Procurement::query()->forceDelete();
    }

    /**
     * @return array{procurement: Procurement, uploadStage1Id: int, uploadStage2Id: int, nonUploadStageId: int}
     */
    private function createProcurementWithStages(string $suffix = '001'): array
    {
        $jobType = JobType::create(['name' => 'Konstruksi CF ' . $suffix, 'is_active' => true]);
        $qualification = Qualification::create(['name' => 'Kecil CF ' . $suffix, 'is_active' => true]);
        $fundingSource = FundingSource::create(['name' => 'APBN CF ' . $suffix, 'is_active' => true]);
        $method = ProcurementMethod::create(['name' => 'Tender CF ' . $suffix, 'is_active' => true]);

        ProcurementMethodStage::create([
            'procurement_method_id' => $method->id,
            'sequence'              => 1,
            'name'                  => 'Pengumuman',
            'duration_days'         => 2,
            'day_type'              => 'working_day',
            'requires_file'         => false,
            'is_public'             => true,
            'page_key'              => 'announcement',
            'process_key'           => 'announce',
            'is_active'             => true,
        ]);

        ProcurementMethodStage::create([
            'procurement_method_id' => $method->id,
            'sequence'              => 2,
            'name'                  => 'Upload Dokumen Kualifikasi',
            'duration_days'         => 3,
            'day_type'              => 'working_day',
            'requires_file'         => true,
            'is_public'             => true,
            'page_key'              => 'qualification_upload',
            'process_key'           => 'upload_qualification',
            'is_active'             => true,
        ]);

        ProcurementMethodStage::create([
            'procurement_method_id' => $method->id,
            'sequence'              => 3,
            'name'                  => 'Upload Dokumen Penawaran',
            'duration_days'         => 4,
            'day_type'              => 'working_day',
            'requires_file'         => true,
            'is_public'             => true,
            'page_key'              => 'bid_upload',
            'process_key'           => 'upload_bid',
            'is_active'             => true,
        ]);

        $procurementId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement', [
                'job_number'            => 'PROC-CF-' . $suffix,
                'name'                  => 'Paket Uji Checklist File ' . $suffix,
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

        $procurement = Procurement::findOrFail($procurementId);
        $schedules = $procurement->schedules()->orderBy('sequence')->get();

        return [
            'procurement'      => $procurement,
            'nonUploadStageId' => (int) $schedules[0]->id,
            'uploadStage1Id'   => (int) $schedules[1]->id,
            'uploadStage2Id'   => (int) $schedules[2]->id,
        ];
    }

    public function test_can_create_multiple_checklist_files_on_stage_filter_and_show(): void
    {
        $ctx = $this->createProcurementWithStages('CRT-1');
        $procurement = $ctx['procurement'];
        $stage1Id = $ctx['uploadStage1Id'];
        $stage2Id = $ctx['uploadStage2Id'];

        $item1 = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $stage1Id,
                'name'                    => 'Akta Perusahaan & Perubahan',
                'max_size'                => 5120,
                'file_types'              => ['PDF', 'zip'],
                'is_required'             => true,
                'description'             => 'Scan akta asli dalam 1 file PDF atau ZIP',
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertSame('Akta Perusahaan & Perubahan', $item1['name']);
        $this->assertSame(5120, $item1['max_size']);
        $this->assertSame(['pdf', 'zip'], $item1['file_types']);
        $this->assertTrue($item1['is_required']);
        $this->assertSame($stage1Id, $item1['procurement_schedule_id']);
        $this->assertSame('Upload Dokumen Kualifikasi', $item1['schedule']['name']);

        $item2 = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $stage1Id,
                'name'                    => 'NPWP & PKP',
                'max_size'                => 2048,
                'file_types'              => ['pdf', 'jpg', 'png'],
                'is_required'             => false,
                'description'             => 'Opsional bagi non-PKP',
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertFalse($item2['is_required']);

        $item3 = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $stage2Id,
                'name'                    => 'RAB / BOQ Penawaran',
                'max_size'                => 10240,
                'file_types'              => ['xls', 'xlsx', 'pdf'],
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertTrue($item3['is_required']);

        $allList = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/checklist-file")
            ->assertStatus(201)
            ->json('result');

        $this->assertCount(3, $allList);

        $filteredList = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/checklist-file?procurement_schedule_id={$stage1Id}")
            ->assertStatus(201)
            ->json('result');

        $this->assertCount(2, $filteredList);
        $this->assertSame($item1['id'], $filteredList[0]['id']);
        $this->assertSame($item2['id'], $filteredList[1]['id']);

        $shown = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/checklist-file/{$item3['id']}")
            ->assertStatus(201)
            ->json('result');

        $this->assertSame('RAB / BOQ Penawaran', $shown['name']);
    }

    public function test_can_update_and_delete_checklist_file(): void
    {
        $ctx = $this->createProcurementWithStages('UPD-1');
        $procurement = $ctx['procurement'];
        $stage1Id = $ctx['uploadStage1Id'];
        $stage2Id = $ctx['uploadStage2Id'];

        $created = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $stage1Id,
                'name'                    => 'Dokumen Teknis Awal',
                'max_size'                => 4096,
                'file_types'              => ['pdf'],
                'is_required'             => true,
                'description'             => 'Deskripsi awal',
            ])
            ->assertStatus(201)
            ->json('result');

        $updated = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file/{$created['id']}", [
                'procurement_schedule_id' => $stage2Id,
                'name'                    => 'Dokumen Teknis Revisi',
                'max_size'                => 8192,
                'file_types'              => ['pdf', 'docx', 'dwg'],
                'is_required'             => false,
                'description'             => 'Deskripsi revisi',
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertSame($stage2Id, $updated['procurement_schedule_id']);
        $this->assertSame('Dokumen Teknis Revisi', $updated['name']);
        $this->assertSame(8192, $updated['max_size']);
        $this->assertSame(['pdf', 'docx', 'dwg'], $updated['file_types']);
        $this->assertFalse($updated['is_required']);
        $this->assertSame('Deskripsi revisi', $updated['description']);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}/checklist-file/{$created['id']}")
            ->assertStatus(201);

        $this->assertSoftDeleted('procurement_checklist_files', ['id' => $created['id']]);
    }

    public function test_rejects_stage_with_requires_file_false_other_procurement_stage_invalid_extensions_and_cancelled_procurement(): void
    {
        $ctx1 = $this->createProcurementWithStages('VAL-1');
        $ctx2 = $this->createProcurementWithStages('VAL-2');
        $procurement = $ctx1['procurement'];

        // Stage with requires_file = false
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $ctx1['nonUploadStageId'],
                'name'                    => 'File di Tahap Tanpa Upload',
                'max_size'                => 1024,
                'file_types'              => ['pdf'],
            ])
            ->assertStatus(400);

        // Stage belonging to another procurement
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $ctx2['uploadStage1Id'],
                'name'                    => 'File Tahap Pengadaan Lain',
                'max_size'                => 1024,
                'file_types'              => ['pdf'],
            ])
            ->assertStatus(400);

        // Disallowed extension
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $ctx1['uploadStage1Id'],
                'name'                    => 'File Ekstensi Berbahaya',
                'max_size'                => 1024,
                'file_types'              => ['pdf', 'exe'],
            ])
            ->assertStatus(400);

        // Valid item before cancel
        $validItem = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $ctx1['uploadStage1Id'],
                'name'                    => 'Dokumen Valid',
                'max_size'                => 2048,
                'file_types'              => ['pdf'],
            ])
            ->assertStatus(201)
            ->json('result');

        // Updating to a stage with requires_file = false is rejected
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file/{$validItem['id']}", [
                'procurement_schedule_id' => $ctx1['nonUploadStageId'],
                'name'                    => 'Pindah ke Tahap Non Upload',
                'max_size'                => 2048,
                'file_types'              => ['pdf'],
            ])
            ->assertStatus(400);

        // Cancel procurement
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/cancel", [
                'reason' => 'Pengadaan dibatalkan',
            ])
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $ctx1['uploadStage1Id'],
                'name'                    => 'Tambah Setelah Batal',
                'max_size'                => 2048,
                'file_types'              => ['pdf'],
            ])
            ->assertStatus(400);

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file/{$validItem['id']}", [
                'name'       => 'Update Setelah Batal',
                'max_size'   => 2048,
                'file_types' => ['pdf'],
            ])
            ->assertStatus(400);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}/checklist-file/{$validItem['id']}")
            ->assertStatus(400);
    }

    public function test_permissions_and_cleanup_on_delete_procurement(): void
    {
        $ctx = $this->createProcurementWithStages('PERM-1');
        $procurement = $ctx['procurement'];

        $item = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $ctx['uploadStage1Id'],
                'name'                    => 'Surat Penawaran',
                'max_size'                => 2048,
                'file_types'              => ['pdf'],
            ])
            ->assertStatus(201)
            ->json('result');

        $readPerm = Permission::where('subject', 'procurement_checklist_file')
            ->where('action', 'read')
            ->firstOrFail();
        $role = Role::create(['name' => 'checklist_reader', 'description' => 'Checklist Reader']);
        $role->permissions()->sync([$readPerm->id]);

        $reader = User::create([
            'name'     => 'Checklist Reader User',
            'username' => 'checklist_reader_user',
            'email'    => 'checklist_reader@example.com',
            'password' => bcrypt('password'),
        ]);
        $reader->roles()->sync([$role->id]);

        $token = auth('api')->login($reader);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->withHeaders($headers)
            ->getJson("/api/procurement/{$procurement->id}/checklist-file")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->withHeaders($headers)
            ->getJson("/api/procurement/{$procurement->id}/checklist-file/{$item['id']}")
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurement->id}/checklist-file", [
                'procurement_schedule_id' => $ctx['uploadStage1Id'],
                'name'                    => 'Forbidden Create',
                'max_size'                => 1024,
                'file_types'              => ['pdf'],
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurement->id}/checklist-file/{$item['id']}", [
                'name'       => 'Forbidden Update',
                'max_size'   => 1024,
                'file_types' => ['pdf'],
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson("/api/procurement/{$procurement->id}/checklist-file/{$item['id']}")
            ->assertStatus(403);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}")
            ->assertStatus(201);

        $this->assertSoftDeleted('procurement_checklist_files', ['id' => $item['id']]);
    }
}
