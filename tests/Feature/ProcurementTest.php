<?php

namespace Tests\Feature;

use App\Models\FundingSource;
use App\Models\JobType;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\Qualification;
use App\Models\Role;
use App\Models\User;

class ProcurementTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Procurement::query()->forceDelete();
    }

    protected function tearDown(): void
    {
        Procurement::query()->forceDelete();
        parent::tearDown();
    }

    private function createMasterData(): array
    {
        $jobType = JobType::create([
            'name'      => 'Pekerjaan Konstruksi Uji',
            'is_active' => true,
        ]);

        $qualification = Qualification::create([
            'name'        => 'Menengah Uji',
            'description' => 'Kualifikasi menengah',
            'is_active'   => true,
        ]);

        $fundingSource = FundingSource::create([
            'name'        => 'APBN Uji',
            'description' => 'Anggaran Pendapatan dan Belanja Negara',
            'is_active'   => true,
        ]);

        $procurementMethod = ProcurementMethod::create([
            'name'        => 'Tender Umum Uji',
            'description' => 'Metode tender umum',
            'is_active'   => true,
        ]);

        return compact('jobType', 'qualification', 'fundingSource', 'procurementMethod');
    }

    public function test_can_create_list_show_update_and_delete_procurement(): void
    {
        $masters = $this->createMasterData();

        $payload = [
            'job_number'            => 'PROC-2026-001',
            'name'                  => 'Pembangunan Gedung Kantor',
            'budget_ceiling'        => 5000000000,
            'estimated_price'       => 4750000000,
            'planned_date'          => '2026-11-15',
            'job_type_id'           => $masters['jobType']->id,
            'qualification_id'      => $masters['qualification']->id,
            'funding_source_id'     => $masters['fundingSource']->id,
            'budget_year'           => 2026,
            'project_location'      => 'Jakarta Pusat',
            'procurement_method_id' => $masters['procurementMethod']->id,
            'announcement_location' => 'Portal E-Procurement',
        ];

        $create = $this->actingAsApiUser()->postJson('/api/procurement', $payload);

        $create->assertStatus(201)
            ->assertJsonPath('result.job_number', 'PROC-2026-001')
            ->assertJsonPath('result.name', 'Pembangunan Gedung Kantor')
            ->assertJsonPath('result.budget_year', 2026)
            ->assertJsonPath('result.is_active', true)
            ->assertJsonPath('result.job_type.id', $masters['jobType']->id)
            ->assertJsonPath('result.qualification.id', $masters['qualification']->id)
            ->assertJsonPath('result.funding_source.id', $masters['fundingSource']->id)
            ->assertJsonPath('result.procurement_method.id', $masters['procurementMethod']->id);

        $id = $create->json('result.id');

        $list = $this->actingAsApiUser()
            ->getJson('/api/procurement?all=true')
            ->assertStatus(201)
            ->assertJsonPath('result.0.id', $id);

        $firstItem = $list->json('result.0');
        $this->assertEquals([
            'id',
            'name',
            'budget_ceiling',
            'estimated_price',
            'budget_year',
            'job_type',
            'procurement_method',
            'qualification',
            'status',
        ], array_keys($firstItem));

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$id}")
            ->assertStatus(201)
            ->assertJsonPath('result.id', $id)
            ->assertJsonPath('result.job_number', 'PROC-2026-001');

        $updatePayload = array_merge($payload, [
            'name'             => 'Pembangunan Gedung Kantor Tahap 1',
            'project_location' => 'Jakarta Selatan',
        ]);

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$id}", $updatePayload)
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Pembangunan Gedung Kantor Tahap 1')
            ->assertJsonPath('result.project_location', 'Jakarta Selatan');

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$id}")
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$id}")
            ->assertStatus(403);

        $this->assertNotNull(Procurement::withTrashed()->find($id));
    }

    public function test_validation_rejects_duplicate_job_number_and_missing_fields(): void
    {
        $this->actingAsApiUser()
            ->postJson('/api/procurement', [])
            ->assertStatus(400);

        $masters = $this->createMasterData();

        $payload = [
            'job_number'            => 'PROC-DUP-001',
            'name'                  => 'Pengadaan Laptop',
            'budget_ceiling'        => 100000000,
            'estimated_price'       => 95000000,
            'planned_date'          => '2026-12-01',
            'job_type_id'           => $masters['jobType']->id,
            'qualification_id'      => $masters['qualification']->id,
            'funding_source_id'     => $masters['fundingSource']->id,
            'budget_year'           => 2026,
            'project_location'      => 'Bandung',
            'procurement_method_id' => $masters['procurementMethod']->id,
            'announcement_location' => 'Website Resmi',
        ];

        $this->actingAsApiUser()
            ->postJson('/api/procurement', $payload)
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson('/api/procurement', $payload)
            ->assertStatus(400);
    }

    public function test_filter_by_name_year_job_type_procurement_method_and_qualification(): void
    {
        $masters1 = $this->createMasterData();

        $jobType2 = JobType::create(['name' => 'Jasa Konsultansi Uji', 'is_active' => true]);
        $qualification2 = Qualification::create(['name' => 'Kecil Uji', 'is_active' => true]);
        $fundingSource2 = FundingSource::create(['name' => 'APBD Uji', 'is_active' => true]);
        $method2 = ProcurementMethod::create(['name' => 'Seleksi Langsung Uji', 'is_active' => true]);

        Procurement::create([
            'job_number'            => 'PROC-FLT-001',
            'name'                  => 'Pembangunan Jembatan Merah',
            'budget_ceiling'        => 3000000000,
            'estimated_price'       => 2800000000,
            'planned_date'          => '2025-05-10',
            'job_type_id'           => $masters1['jobType']->id,
            'qualification_id'      => $masters1['qualification']->id,
            'funding_source_id'     => $masters1['fundingSource']->id,
            'budget_year'           => 2025,
            'project_location'      => 'Surabaya',
            'procurement_method_id' => $masters1['procurementMethod']->id,
            'announcement_location' => 'Portal',
            'is_active'             => true,
        ]);

        Procurement::create([
            'job_number'            => 'PROC-FLT-002',
            'name'                  => 'Pengawasan Renovasi Gedung',
            'budget_ceiling'        => 500000000,
            'estimated_price'       => 450000000,
            'planned_date'          => '2026-08-20',
            'job_type_id'           => $jobType2->id,
            'qualification_id'      => $qualification2->id,
            'funding_source_id'     => $fundingSource2->id,
            'budget_year'           => 2026,
            'project_location'      => 'Medan',
            'procurement_method_id' => $method2->id,
            'announcement_location' => 'Papan Pengumuman',
            'is_active'             => true,
        ]);

        $this->actingAsApiUser()
            ->getJson('/api/procurement?filter[name]=Jembatan')
            ->assertStatus(201)
            ->assertJsonCount(1, 'result')
            ->assertJsonPath('result.0.name', 'Pembangunan Jembatan Merah');

        $this->actingAsApiUser()
            ->getJson('/api/procurement?filter[tahun]=2026')
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'Pengawasan Renovasi Gedung');

        $this->actingAsApiUser()
            ->getJson('/api/procurement?filter[jenis_pekerjaan]=Konsultansi')
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'Pengawasan Renovasi Gedung');

        $this->actingAsApiUser()
            ->getJson('/api/procurement?filter[metode_pengadaan]=Seleksi')
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'Pengawasan Renovasi Gedung');

        $this->actingAsApiUser()
            ->getJson('/api/procurement?filter[kualifikasi]=Kecil')
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'Pengawasan Renovasi Gedung');
    }

    public function test_id_is_autoincrement_integer(): void
    {
        $masters = $this->createMasterData();

        $p1 = $this->actingAsApiUser()->postJson('/api/procurement', [
            'job_number'            => 'PROC-SEQ-001',
            'name'                  => 'Paket Seq 1',
            'budget_ceiling'        => 1000000,
            'estimated_price'       => 900000,
            'planned_date'          => '2026-01-01',
            'job_type_id'           => $masters['jobType']->id,
            'qualification_id'      => $masters['qualification']->id,
            'funding_source_id'     => $masters['fundingSource']->id,
            'budget_year'           => 2026,
            'project_location'      => 'Loc 1',
            'procurement_method_id' => $masters['procurementMethod']->id,
            'announcement_location' => 'Ann 1',
        ])->json('result.id');

        $p2 = $this->actingAsApiUser()->postJson('/api/procurement', [
            'job_number'            => 'PROC-SEQ-002',
            'name'                  => 'Paket Seq 2',
            'budget_ceiling'        => 2000000,
            'estimated_price'       => 1900000,
            'planned_date'          => '2026-01-01',
            'job_type_id'           => $masters['jobType']->id,
            'qualification_id'      => $masters['qualification']->id,
            'funding_source_id'     => $masters['fundingSource']->id,
            'budget_year'           => 2026,
            'project_location'      => 'Loc 2',
            'procurement_method_id' => $masters['procurementMethod']->id,
            'announcement_location' => 'Ann 2',
        ])->json('result.id');

        $this->assertIsInt($p1);
        $this->assertIsInt($p2);
        $this->assertGreaterThan($p1, $p2);
    }

    public function test_can_cancel_procurement_with_permission(): void
    {
        $masters = $this->createMasterData();

        $create = $this->actingAsApiUser()->postJson('/api/procurement', [
            'job_number'            => 'PROC-CANCEL-001',
            'name'                  => 'Paket Batal Uji',
            'budget_ceiling'        => 1000000000,
            'estimated_price'       => 950000000,
            'planned_date'          => '2026-11-20',
            'job_type_id'           => $masters['jobType']->id,
            'qualification_id'      => $masters['qualification']->id,
            'funding_source_id'     => $masters['fundingSource']->id,
            'budget_year'           => 2026,
            'project_location'      => 'Jakarta',
            'procurement_method_id' => $masters['procurementMethod']->id,
            'announcement_location' => 'Portal',
        ]);

        $id = $create->json('result.id');

        $cancelRes = $this->actingAsApiUser()->postJson("/api/procurement/{$id}/cancel", [
            'reason' => 'Perubahan prioritas anggaran pemerintah',
        ]);

        $cancelRes->assertStatus(201)
            ->assertJsonPath('result.status', 'cancelled')
            ->assertJsonPath('result.cancellation_reason', 'Perubahan prioritas anggaran pemerintah');

        $this->assertNotNull($cancelRes->json('result.cancelled_at'));

        // Cannot update cancelled procurement
        $this->actingAsApiUser()->postJson("/api/procurement/{$id}", [
            'job_number'            => 'PROC-CANCEL-001',
            'name'                  => 'Paket Coba Update',
            'budget_ceiling'        => 1000000000,
            'estimated_price'       => 950000000,
            'planned_date'          => '2026-11-20',
            'job_type_id'           => $masters['jobType']->id,
            'qualification_id'      => $masters['qualification']->id,
            'funding_source_id'     => $masters['fundingSource']->id,
            'budget_year'           => 2026,
            'project_location'      => 'Jakarta',
            'procurement_method_id' => $masters['procurementMethod']->id,
            'announcement_location' => 'Portal',
        ])->assertStatus(400);

        // Cannot cancel already cancelled procurement
        $this->actingAsApiUser()->postJson("/api/procurement/{$id}/cancel", [
            'reason' => 'Batal lagi',
        ])->assertStatus(400);
    }

    public function test_cancel_procurement_requires_permission(): void
    {
        $masters = $this->createMasterData();

        $proc = Procurement::create([
            'job_number'            => 'PROC-CANCEL-NOAUTH',
            'name'                  => 'Paket Batal Tanpa Hak Akses',
            'budget_ceiling'        => 500000000,
            'estimated_price'       => 450000000,
            'planned_date'          => '2026-11-20',
            'job_type_id'           => $masters['jobType']->id,
            'qualification_id'      => $masters['qualification']->id,
            'funding_source_id'     => $masters['fundingSource']->id,
            'budget_year'           => 2026,
            'project_location'      => 'Jakarta',
            'procurement_method_id' => $masters['procurementMethod']->id,
            'announcement_location' => 'Portal',
            'is_active'             => true,
            'status'                => 'draft',
        ]);

        $role = Role::create(['name' => 'procurement_viewer_' . uniqid(), 'description' => 'Viewer']);
        $user = User::create([
            'name'          => 'Viewer User',
            'username'      => 'viewer_' . uniqid(),
            'email'         => 'viewer_' . uniqid() . '@eproc.test',
            'password'      => bcrypt('Password123!'),
            'role_id'       => $role->id,
            'is_active'     => true,
            'is_verify'     => true,
        ]);
        $user->roles()->attach($role->id);
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/procurement/{$proc->id}/cancel", ['reason' => 'Mau batal'])
            ->assertStatus(403);
    }
}
