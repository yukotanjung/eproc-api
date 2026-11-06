<?php

namespace Tests\Feature;

use App\Models\FundingSource;
use App\Models\JobField;
use App\Models\JobSubField;
use App\Models\JobType;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\Qualification;
use App\Models\Role;
use App\Models\User;

class ProcurementJobSubFieldTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Procurement::query()->forceDelete();
    }

    private function createProcurementWithHierarchy(string $suffix = '001'): array
    {
        $jobType = JobType::create([
            'name'      => 'Pekerjaan Konstruksi ' . $suffix,
            'is_active' => true,
        ]);

        $jobField = JobField::create([
            'job_type_id' => $jobType->id,
            'name'        => 'Jasa Pelaksanaan Lainnya ' . $suffix,
            'is_active'   => true,
        ]);

        $subFieldA = JobSubField::create([
            'job_type_id'  => $jobType->id,
            'job_field_id' => $jobField->id,
            'name'         => 'PL001 Sub Bidang A ' . $suffix,
            'is_active'    => true,
        ]);

        $subFieldB = JobSubField::create([
            'job_type_id'  => $jobType->id,
            'job_field_id' => $jobField->id,
            'name'         => 'PL002 Sub Bidang B ' . $suffix,
            'is_active'    => true,
        ]);

        $qualification = Qualification::create([
            'name'      => 'Menengah ' . $suffix,
            'is_active' => true,
        ]);

        $fundingSource = FundingSource::create([
            'name'      => 'RKAP ' . $suffix,
            'is_active' => true,
        ]);

        $method = ProcurementMethod::create([
            'name'      => 'Tender Umum ' . $suffix,
            'is_active' => true,
        ]);

        $procurementId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement', [
                'job_number'            => 'PROC-JSF-' . $suffix,
                'name'                  => 'Paket Uji Sub Bidang ' . $suffix,
                'budget_ceiling'        => 1000000000,
                'estimated_price'       => 950000000,
                'planned_date'          => '2026-02-04',
                'job_type_id'           => $jobType->id,
                'qualification_id'      => $qualification->id,
                'funding_source_id'     => $fundingSource->id,
                'budget_year'           => 2026,
                'project_location'      => 'Jakarta',
                'procurement_method_id' => $method->id,
                'announcement_location' => 'Portal E-Proc',
            ])
            ->assertStatus(201)
            ->json('result.id');

        return compact('procurementId', 'jobType', 'jobField', 'subFieldA', 'subFieldB');
    }

    public function test_can_sync_and_list_job_sub_fields_for_procurement(): void
    {
        $ctx = $this->createProcurementWithHierarchy('SYNC-1');
        $procurementId = $ctx['procurementId'];

        $syncResponse = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldA']->id, $ctx['subFieldB']->id],
            ])
            ->assertStatus(201);

        $syncData = $syncResponse->json('result');
        $this->assertCount(2, $syncData);
        $this->assertSame($ctx['subFieldA']->id, $syncData[0]['id']);
        $this->assertSame($ctx['subFieldA']->name, $syncData[0]['name']);
        $this->assertSame($ctx['jobType']->id, $syncData[0]['job_type']['id']);
        $this->assertSame($ctx['jobType']->name, $syncData[0]['job_type']['name']);
        $this->assertSame($ctx['jobField']->id, $syncData[0]['job_field']['id']);
        $this->assertSame($ctx['jobField']->name, $syncData[0]['job_field']['name']);

        $listResponse = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/job-sub-field")
            ->assertStatus(201);

        $listData = $listResponse->json('result');
        $this->assertCount(2, $listData);
        $this->assertEquals(
            [$ctx['subFieldA']->id, $ctx['subFieldB']->id],
            array_column($listData, 'id')
        );
    }

    public function test_can_replace_deduplicate_clear_and_delete_single_job_sub_field(): void
    {
        $ctx = $this->createProcurementWithHierarchy('DEL-1');
        $procurementId = $ctx['procurementId'];

        // Sync with duplicate IDs in payload -> deduplicated to 2 items
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [
                    $ctx['subFieldA']->id,
                    $ctx['subFieldA']->id,
                    $ctx['subFieldB']->id,
                ],
            ])
            ->assertStatus(201)
            ->assertJsonCount(2, 'result');

        // Delete single item (subFieldA)
        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurementId}/job-sub-field/{$ctx['subFieldA']->id}")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result')
            ->assertJsonPath('result.0.id', $ctx['subFieldB']->id);

        // Deleting an already-removed item returns 403 invalidResponse ('No data')
        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurementId}/job-sub-field/{$ctx['subFieldA']->id}")
            ->assertStatus(403);

        // Clearing via empty array
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [],
            ])
            ->assertStatus(201)
            ->assertJsonCount(0, 'result');
    }

    public function test_validation_rejects_mismatched_job_type_inactive_deleted_and_cancelled_procurement(): void
    {
        $ctx = $this->createProcurementWithHierarchy('VAL-1');
        $procurementId = $ctx['procurementId'];

        $otherJobType = JobType::create(['name' => 'Jasa Konsultansi VAL-1', 'is_active' => true]);
        $otherJobField = JobField::create([
            'job_type_id' => $otherJobType->id,
            'name'        => 'Bidang Konsultansi VAL-1',
            'is_active'   => true,
        ]);
        $subFieldOtherType = JobSubField::create([
            'job_type_id'  => $otherJobType->id,
            'job_field_id' => $otherJobField->id,
            'name'         => 'Sub Bidang Tipe Lain VAL-1',
            'is_active'    => true,
        ]);

        // 1. Mismatched job_type_id must be rejected with 400
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldA']->id, $subFieldOtherType->id],
            ])
            ->assertStatus(400);

        // 2. Inactive job_sub_field must be rejected with 400
        $inactiveSubField = JobSubField::create([
            'job_type_id'  => $ctx['jobType']->id,
            'job_field_id' => $ctx['jobField']->id,
            'name'         => 'Sub Bidang Nonaktif VAL-1',
            'is_active'    => false,
        ]);
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$inactiveSubField->id],
            ])
            ->assertStatus(400);

        // 3. Soft-deleted job_sub_field must be rejected with 400
        $deletedSubField = JobSubField::create([
            'job_type_id'  => $ctx['jobType']->id,
            'job_field_id' => $ctx['jobField']->id,
            'name'         => 'Sub Bidang Dihapus VAL-1',
            'is_active'    => true,
        ]);
        $deletedSubField->delete();

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$deletedSubField->id],
            ])
            ->assertStatus(400);

        // Attach valid subFieldA first, then cancel procurement
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldA']->id],
            ])
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/cancel", [
                'reason' => 'Dibatalkan untuk uji',
            ])
            ->assertStatus(201);

        // 4. Cannot sync or delete on cancelled procurement
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldB']->id],
            ])
            ->assertStatus(400);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurementId}/job-sub-field/{$ctx['subFieldA']->id}")
            ->assertStatus(400);
    }

    public function test_changing_procurement_job_type_resets_job_sub_fields_while_other_updates_keep_them(): void
    {
        $ctx = $this->createProcurementWithHierarchy('RESET-1');
        $procurementId = $ctx['procurementId'];
        $procurement = Procurement::findOrFail($procurementId);

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldA']->id, $ctx['subFieldB']->id],
            ])
            ->assertStatus(201)
            ->assertJsonCount(2, 'result');

        // Update name only (same job_type_id) -> job_sub_fields must stay intact
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}", [
                'job_number'            => $procurement->job_number,
                'name'                  => 'Nama Paket Diperbarui',
                'budget_ceiling'        => $procurement->budget_ceiling,
                'estimated_price'       => $procurement->estimated_price,
                'planned_date'          => $procurement->planned_date->format('Y-m-d'),
                'job_type_id'           => $procurement->job_type_id,
                'qualification_id'      => $procurement->qualification_id,
                'funding_source_id'     => $procurement->funding_source_id,
                'budget_year'           => $procurement->budget_year,
                'project_location'      => $procurement->project_location,
                'procurement_method_id' => $procurement->procurement_method_id,
                'announcement_location' => $procurement->announcement_location,
            ])
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/job-sub-field")
            ->assertStatus(201)
            ->assertJsonCount(2, 'result');

        // Update job_type_id to a new JobType -> job_sub_fields must be automatically cleared
        $newJobType = JobType::create(['name' => 'Pengadaan Barang RESET-1', 'is_active' => true]);

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}", [
                'job_number'            => $procurement->job_number,
                'name'                  => 'Nama Paket Diperbarui',
                'budget_ceiling'        => $procurement->budget_ceiling,
                'estimated_price'       => $procurement->estimated_price,
                'planned_date'          => $procurement->planned_date->format('Y-m-d'),
                'job_type_id'           => $newJobType->id,
                'qualification_id'      => $procurement->qualification_id,
                'funding_source_id'     => $procurement->funding_source_id,
                'budget_year'           => $procurement->budget_year,
                'project_location'      => $procurement->project_location,
                'procurement_method_id' => $procurement->procurement_method_id,
                'announcement_location' => $procurement->announcement_location,
            ])
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/job-sub-field")
            ->assertStatus(201)
            ->assertJsonCount(0, 'result');
    }

    public function test_vendor_eligibility_matches_when_at_least_one_job_sub_field_intersects(): void
    {
        $ctx = $this->createProcurementWithHierarchy('MATCH-1');
        $procurementId = $ctx['procurementId'];
        $procurement = Procurement::findOrFail($procurementId);

        $subFieldC = JobSubField::create([
            'job_type_id'  => $ctx['jobType']->id,
            'job_field_id' => $ctx['jobField']->id,
            'name'         => 'PL003 Sub Bidang C MATCH-1',
            'is_active'    => true,
        ]);

        // When procurement has no job_sub_fields yet -> false
        $this->assertFalse($procurement->matchesVendorJobSubFields([$ctx['subFieldA']->id]));

        // Configure procurement with [subFieldA, subFieldB]
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldA']->id, $ctx['subFieldB']->id],
            ])
            ->assertStatus(201);

        // Vendor has 1 matching sub-field (OR rule) -> true
        $this->assertTrue($procurement->matchesVendorJobSubFields([$ctx['subFieldA']->id, $subFieldC->id]));
        $this->assertTrue($procurement->matchesVendorJobSubFields([$ctx['subFieldB']->id]));

        // Vendor has all matching sub-fields -> true
        $this->assertTrue($procurement->matchesVendorJobSubFields([$ctx['subFieldA']->id, $ctx['subFieldB']->id]));

        // Vendor only has non-matching sub-field -> false
        $this->assertFalse($procurement->matchesVendorJobSubFields([$subFieldC->id]));

        // Vendor has empty sub-fields -> false
        $this->assertFalse($procurement->matchesVendorJobSubFields([]));
    }

    public function test_permissions_and_delete_procurement_cleanup(): void
    {
        $ctx = $this->createProcurementWithHierarchy('PERM-1');
        $procurementId = $ctx['procurementId'];

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldA']->id],
            ])
            ->assertStatus(201);

        $readPerm = Permission::where('subject', 'procurement')->where('action', 'read')->firstOrFail();
        $role = Role::create(['name' => 'viewer_jsf_test', 'description' => 'Viewer JSF']);
        $role->permissions()->sync([$readPerm->id]);

        $viewer = User::create([
            'name'     => 'Viewer Sub Bidang',
            'username' => 'viewer_jsf_user',
            'email'    => 'viewer_jsf@example.com',
            'password' => bcrypt('password'),
        ]);
        $viewer->roles()->sync([$role->id]);

        $token = auth('api')->login($viewer);
        $headers = ['Authorization' => 'Bearer ' . $token];

        // Read allowed with procurement_read
        $this->withHeaders($headers)
            ->getJson("/api/procurement/{$procurementId}/job-sub-field")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        // Sync and Delete forbidden without procurement_update
        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurementId}/job-sub-field", [
                'job_sub_field_ids' => [$ctx['subFieldB']->id],
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson("/api/procurement/{$procurementId}/job-sub-field/{$ctx['subFieldA']->id}")
            ->assertStatus(403);

        // Deleting procurement cleans up pivot rows
        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurementId}")
            ->assertStatus(201);

        $this->assertSame(
            0,
            \Illuminate\Support\Facades\DB::table('procurement_job_sub_fields')
                ->where('procurement_id', $procurementId)
                ->count()
        );
    }
}
