<?php

namespace Tests\Feature;

use App\Models\FundingSource;
use App\Models\Holiday;
use App\Models\JobType;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\ProcurementMethod;
use App\Models\ProcurementMethodStage;
use App\Models\Qualification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProcurementScheduleTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Procurement::query()->forceDelete();
        Holiday::query()->forceDelete();
    }

    private function createMethodWithStages(string $methodName = 'Metode Uji Jadwal'): ProcurementMethod
    {
        $method = ProcurementMethod::create([
            'name'        => $methodName,
            'description' => 'Metode pengujian jadwal',
            'is_active'   => true,
        ]);

        ProcurementMethodStage::create([
            'procurement_method_id' => $method->id,
            'sequence'              => 1,
            'name'                  => 'Pengumuman',
            'duration_days'         => 3,
            'day_type'              => ProcurementMethodStage::DAY_TYPE_CALENDAR,
            'requires_file'         => false,
            'is_public'             => true,
            'page_key'              => 'procurement/stage/pengumuman',
            'process_key'           => 'stage.pengumuman',
            'is_active'             => true,
        ]);

        ProcurementMethodStage::create([
            'procurement_method_id' => $method->id,
            'sequence'              => 2,
            'name'                  => 'Aanwijzing',
            'duration_days'         => 3,
            'day_type'              => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            'requires_file'         => false,
            'is_public'             => true,
            'page_key'              => 'procurement/stage/aanwijzing',
            'process_key'           => 'stage.aanwijzing',
            'is_active'             => true,
        ]);

        ProcurementMethodStage::create([
            'procurement_method_id' => $method->id,
            'sequence'              => 3,
            'name'                  => 'Evaluasi Internal Panitia',
            'duration_days'         => 2,
            'day_type'              => ProcurementMethodStage::DAY_TYPE_WORKING_DAY,
            'requires_file'         => true,
            'is_public'             => false,
            'page_key'              => 'procurement/stage/evaluasi',
            'process_key'           => 'stage.evaluasi',
            'is_active'             => true,
        ]);

        ProcurementMethodStage::create([
            'procurement_method_id' => $method->id,
            'sequence'              => 4,
            'name'                  => 'Pengumuman Pemenang',
            'duration_days'         => 2,
            'day_type'              => ProcurementMethodStage::DAY_TYPE_CALENDAR,
            'requires_file'         => false,
            'is_public'             => true,
            'page_key'              => 'procurement/stage/pemenang',
            'process_key'           => 'stage.pemenang',
            'is_active'             => true,
        ]);

        return $method;
    }

    private function createProcurementViaApi(ProcurementMethod $method, string $jobNumber = 'PROC-SCH-001', string $plannedDate = '2026-02-04'): int
    {
        $jobType = JobType::create(['name' => 'Konstruksi '.$jobNumber, 'is_active' => true]);
        $qualification = Qualification::create(['name' => 'Menengah '.$jobNumber, 'is_active' => true]);
        $fundingSource = FundingSource::create(['name' => 'RKAP '.$jobNumber, 'is_active' => true]);

        return (int) $this->actingAsApiUser()
            ->postJson('/api/procurement', [
                'job_number'            => $jobNumber,
                'name'                  => 'Paket '.$jobNumber,
                'budget_ceiling'        => 1000000000,
                'estimated_price'       => 950000000,
                'planned_date'          => $plannedDate,
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
    }

    public function test_snapshots_are_created_with_empty_dates_on_procurement_creation(): void
    {
        $method = $this->createMethodWithStages();
        $procurementId = $this->createProcurementViaApi($method);

        $res = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/schedule")
            ->assertStatus(201)
            ->assertJsonCount(4, 'result')
            ->assertJsonPath('result.0.sequence', 1)
            ->assertJsonPath('result.0.name', 'Pengumuman')
            ->assertJsonPath('result.0.is_public', true)
            ->assertJsonPath('result.0.start_at', null)
            ->assertJsonPath('result.0.end_at', null)
            ->assertJsonPath('result.2.sequence', 3)
            ->assertJsonPath('result.2.is_public', false);

        // Editing master stage afterwards must not mutate existing procurement's snapshot
        ProcurementMethodStage::where('procurement_method_id', $method->id)
            ->where('sequence', 1)
            ->update(['duration_days' => 99, 'name' => 'Berubah di Master']);

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/schedule")
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'Pengumuman')
            ->assertJsonPath('result.0.duration_days', 3);
    }

    public function test_auto_generate_preview_and_save_respect_weekends_and_holidays(): void
    {
        // Feb 4, 2026 = Wednesday.
        // Stage 1 (calendar, 3d): Wed Feb 4 .. Fri Feb 6
        // Stage 2 (working_day, 3d): snaps past Sat Feb 7 / Sun Feb 8 -> Mon Feb 9;
        //   if Tue Feb 10 is holiday -> Mon Feb 9, Wed Feb 11, Thu Feb 12
        // Stage 3 (working_day, 2d): Fri Feb 13, Mon Feb 16
        // Stage 4 (calendar, 2d): Tue Feb 17 .. Wed Feb 18
        Holiday::create([
            'holiday_date' => '2026-02-10',
            'name'         => 'Libur Nasional Uji',
            'is_active'    => true,
        ]);

        $method = $this->createMethodWithStages();
        $procurementId = $this->createProcurementViaApi($method, 'PROC-GEN-001', '2026-02-04');

        // Preview mode (default save=false) does not persist
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule/generate", [])
            ->assertStatus(201)
            ->assertJsonPath('result.saved', false)
            ->assertJsonPath('result.schedules.0.start_at', '2026-02-04 08:00')
            ->assertJsonPath('result.schedules.0.end_at', '2026-02-06 16:00')
            ->assertJsonPath('result.schedules.1.start_at', '2026-02-09 08:00')
            ->assertJsonPath('result.schedules.1.end_at', '2026-02-12 16:00')
            ->assertJsonPath('result.schedules.2.start_at', '2026-02-13 08:00')
            ->assertJsonPath('result.schedules.2.end_at', '2026-02-16 16:00')
            ->assertJsonPath('result.schedules.3.start_at', '2026-02-17 08:00')
            ->assertJsonPath('result.schedules.3.end_at', '2026-02-18 16:00');

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/schedule")
            ->assertJsonPath('result.0.start_at', null);

        // Save mode (save=true) persists to database
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule/generate", ['save' => true])
            ->assertStatus(201)
            ->assertJsonPath('result.saved', true);

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/schedule")
            ->assertJsonPath('result.1.start_at', '2026-02-09 08:00')
            ->assertJsonPath('result.1.end_at', '2026-02-12 16:00')
            ->assertJsonPath('result.1.actual_duration_days', 3);
    }

    public function test_can_save_shorter_duration_and_partial_contiguous_stages_and_regenerate_remaining(): void
    {
        $method = $this->createMethodWithStages();
        $procurementId = $this->createProcurementViaApi($method, 'PROC-SHORT-001', '2026-03-02');

        // Partial save: Stage 1 (max 3d -> 2d: Mar 2..Mar 3) & Stage 2 (max 3 working days -> 2d: Mar 4..Mar 5), Stages 3 & 4 left null
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    [
                        'sequence'  => 1,
                        'start_at'  => '2026-03-02 09:00',
                        'end_at'    => '2026-03-03 15:00',
                        'is_public' => true,
                    ],
                    [
                        'sequence'  => 2,
                        'start_at'  => '2026-03-04 08:30',
                        'end_at'    => '2026-03-05 16:00',
                        'is_public' => false,
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.0.actual_duration_days', 2)
            ->assertJsonPath('result.1.actual_duration_days', 2)
            ->assertJsonPath('result.1.is_public', false)
            ->assertJsonPath('result.2.start_at', null)
            ->assertJsonPath('result.3.start_at', null);

        // Regenerate remaining stages from sequence 3 -> starts Fri Mar 6
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule/generate", [
                'from_sequence' => 3,
                'save'          => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.schedules.1.end_at', '2026-03-05 16:00')
            ->assertJsonPath('result.schedules.2.start_at', '2026-03-06 08:00')
            ->assertJsonPath('result.schedules.2.end_at', '2026-03-09 16:00')
            ->assertJsonPath('result.schedules.3.start_at', '2026-03-10 08:00')
            ->assertJsonPath('result.schedules.3.end_at', '2026-03-11 16:00');
    }

    public function test_validation_rejects_exceeding_duration_clashes_weekend_working_day_and_skipped_sequence(): void
    {
        Holiday::create([
            'holiday_date' => '2026-04-08',
            'name'         => 'Libur April',
            'is_active'    => true,
        ]);

        $method = $this->createMethodWithStages();
        $procurementId = $this->createProcurementViaApi($method, 'PROC-VAL-001', '2026-04-06');

        // 1) Exceeding duration_days (Stage 1 max 3 calendar days, given 4 days Apr 6..Apr 9)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-04-06 08:00', 'end_at' => '2026-04-09 16:00'],
                ],
            ])
            ->assertStatus(400);

        // 2) Working-day stage starting on a weekend (Sat Apr 11)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-04-06 08:00', 'end_at' => '2026-04-08 16:00'],
                    ['sequence' => 2, 'start_at' => '2026-04-11 08:00', 'end_at' => '2026-04-13 16:00'],
                ],
            ])
            ->assertStatus(400);

        // 3) Working-day stage ending on a holiday (Wed Apr 8)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-04-06 08:00', 'end_at' => '2026-04-06 16:00'],
                    ['sequence' => 2, 'start_at' => '2026-04-07 08:00', 'end_at' => '2026-04-08 16:00'],
                ],
            ])
            ->assertStatus(400);

        // 4) Intra-procurement same-day clash (Stage 2 starts on Stage 1's end_date Apr 7)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-04-06 08:00', 'end_at' => '2026-04-07 10:00'],
                    ['sequence' => 2, 'start_at' => '2026-04-07 13:00', 'end_at' => '2026-04-09 16:00'],
                ],
            ])
            ->assertStatus(400);

        // 5) Non-contiguous partial save (Stage 1 & Stage 3 filled, Stage 2 left null)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-04-06 08:00', 'end_at' => '2026-04-07 16:00'],
                    ['sequence' => 3, 'start_at' => '2026-04-13 08:00', 'end_at' => '2026-04-14 16:00'],
                ],
            ])
            ->assertStatus(400);
    }

    public function test_cross_procurement_conflict_validation_and_auto_generate_shift(): void
    {
        $method = $this->createMethodWithStages('Metode Lintas Paket');
        $procA = $this->createProcurementViaApi($method, 'PROC-CROSS-A', '2026-05-04');
        $procB = $this->createProcurementViaApi($method, 'PROC-CROSS-B', '2026-05-04');

        // Schedule Stage 1 on Procurement A: May 4 08:00 .. May 4 12:00 (1 day)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procA}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-05-04 08:00', 'end_at' => '2026-05-04 12:00'],
                ],
            ])
            ->assertStatus(201);

        // Procurement B overlapping hours on May 4 (10:00 .. 15:00) -> REJECTED (400)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procB}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-05-04 10:00', 'end_at' => '2026-05-04 15:00'],
                ],
            ])
            ->assertStatus(400);

        // Procurement B non-overlapping hours on the same day May 4 (13:00 .. 16:00) -> ALLOWED (201)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procB}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-05-04 13:00', 'end_at' => '2026-05-04 16:00'],
                ],
            ])
            ->assertStatus(201);

        // Now auto-generate full schedule on Procurement A (save=true) from May 11 -> occupies May 11..May 21
        $genA = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procA}/schedule/generate", [
                'start_date' => '2026-05-11',
                'save'       => true,
            ])
            ->assertStatus(201);

        $lastEndA = substr($genA->json('result.schedules.3.end_at'), 0, 10);

        // Auto-generate Procurement B from the same start_date May 11 with default 08:00-16:00
        // Generator must shift past Procurement A's occupied window and save cleanly!
        $genB = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procB}/schedule/generate", [
                'start_date' => '2026-05-11',
                'save'       => true,
            ])
            ->assertStatus(201);

        $firstStartB = substr($genB->json('result.schedules.0.start_at'), 0, 10);
        $this->assertGreaterThan($lastEndA, $firstStartB);

        // If Procurement A is cancelled, its schedule no longer blocks other procurements
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procA}/cancel", ['reason' => 'Dibatalkan'])
            ->assertStatus(201);

        $procC = $this->createProcurementViaApi($method, 'PROC-CROSS-C', '2026-05-11');
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procC}/schedule/generate", [
                'start_date' => '2026-05-11',
                'save'       => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.schedules.0.start_at', '2026-05-11 08:00');
    }

    public function test_public_endpoint_exposes_only_public_stages_without_auth(): void
    {
        $method = $this->createMethodWithStages('Metode Publik');
        $procurementId = $this->createProcurementViaApi($method, 'PROC-PUB-001', '2026-06-01');

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule/generate", ['save' => true])
            ->assertStatus(201);

        // Unauthenticated client can fetch public schedule and sees only 3 public stages (Stage 3 has is_public=false)
        $this->flushHeaders()
            ->getJson("/api/public/procurement/{$procurementId}/schedule")
            ->assertStatus(201)
            ->assertJsonPath('result.procurement.id', $procurementId)
            ->assertJsonCount(3, 'result.schedules')
            ->assertJsonPath('result.schedules.0.sequence', 1)
            ->assertJsonPath('result.schedules.1.sequence', 2)
            ->assertJsonPath('result.schedules.2.sequence', 4);
    }

    public function test_working_day_span_across_weekend_and_holiday_counts_only_working_days(): void
    {
        // Mon Apr 13, 2026 is a holiday.
        // Stage 2 (working_day, max 3 days): Fri Apr 10 .. Tue Apr 14 spans 5 calendar days
        //   but only 2 working days (Fri Apr 10 & Tue Apr 14) -> valid (2 <= 3)!
        Holiday::create([
            'holiday_date' => '2026-04-13',
            'name'         => 'Cuti Bersama Uji',
            'is_active'    => true,
        ]);

        $method = $this->createMethodWithStages('Metode Hari Kerja');
        $procurementId = $this->createProcurementViaApi($method, 'PROC-WD-001', '2026-04-08');

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-04-08 08:00', 'end_at' => '2026-04-09 16:00'],
                    ['sequence' => 2, 'start_at' => '2026-04-10 08:00', 'end_at' => '2026-04-14 16:00'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.1.actual_duration_days', 2);
    }

    public function test_updating_procurement_method_on_draft_resyncs_snapshot_stages(): void
    {
        $method1 = $this->createMethodWithStages('Metode Awal');
        $method2 = ProcurementMethod::create([
            'name'      => 'Metode Pengganti',
            'is_active' => true,
        ]);
        ProcurementMethodStage::create([
            'procurement_method_id' => $method2->id,
            'sequence'              => 1,
            'name'                  => 'Tahap Tunggal',
            'duration_days'         => 4,
            'day_type'              => ProcurementMethodStage::DAY_TYPE_CALENDAR,
            'requires_file'         => false,
            'is_public'             => true,
            'page_key'              => 'procurement/stage/single',
            'process_key'           => 'stage.single',
            'is_active'             => true,
        ]);

        $procurementId = $this->createProcurementViaApi($method1, 'PROC-RESYNC-001', '2026-07-01');
        $proc = Procurement::findOrFail($procurementId);

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurementId}", [
                'job_number'            => $proc->job_number,
                'name'                  => $proc->name,
                'budget_ceiling'        => $proc->budget_ceiling,
                'estimated_price'       => $proc->estimated_price,
                'planned_date'          => '2026-07-01',
                'job_type_id'           => $proc->job_type_id,
                'qualification_id'      => $proc->qualification_id,
                'funding_source_id'     => $proc->funding_source_id,
                'budget_year'           => $proc->budget_year,
                'project_location'      => $proc->project_location,
                'procurement_method_id' => $method2->id,
                'announcement_location' => $proc->announcement_location,
            ])
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurementId}/schedule")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result')
            ->assertJsonPath('result.0.name', 'Tahap Tunggal');
    }

    public function test_read_only_user_can_preview_generate_but_cannot_save_schedule(): void
    {
        $method = $this->createMethodWithStages('Metode Izin');
        $procurementId = $this->createProcurementViaApi($method, 'PROC-PERM-001', '2026-08-03');

        $readPerm = Permission::where('subject', 'procurement')->where('action', 'read')->firstOrFail();
        $role = Role::create(['name' => 'viewer_schedule_test', 'description' => 'Viewer']);
        $role->permissions()->sync([$readPerm->id]);

        $viewer = User::create([
            'name'     => 'Viewer Jadwal',
            'username' => 'viewer_jadwal_test',
            'email'    => 'viewer_jadwal@example.com',
            'password' => bcrypt('password'),
        ]);
        $viewer->roles()->sync([$role->id]);

        $token = auth('api')->login($viewer);
        $headers = ['Authorization' => 'Bearer '.$token];

        // Preview generate (save=false) allowed
        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurementId}/schedule/generate", ['save' => false])
            ->assertStatus(201);

        // Persisting via generate (save=true) forbidden without procurement_update
        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurementId}/schedule/generate", ['save' => true])
            ->assertStatus(403);

        // Bulk save forbidden without procurement_update
        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurementId}/schedule", [
                'schedules' => [
                    ['sequence' => 1, 'start_at' => '2026-08-03 08:00', 'end_at' => '2026-08-04 16:00'],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_new_oracle_tables_have_all_lowercase_columns(): void
    {
        foreach (['holidays', 'procurement_schedules', 'procurement_method_stages'] as $table) {
            $columns = DB::select("SELECT column_name FROM user_tab_columns WHERE table_name = '{$table}'");
            $this->assertNotEmpty($columns, "Expected columns for {$table}");
            foreach ($columns as $c) {
                $this->assertSame(strtolower($c->column_name), $c->column_name);
            }
        }
    }
}
