<?php

namespace Tests\Feature;

use App\Models\FundingSource;
use App\Models\JobType;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\ProcurementHpsItem;
use App\Models\ProcurementMethod;
use App\Models\Qualification;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;

class ProcurementHpsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(UnitSeeder::class);
        Procurement::query()->forceDelete();
    }

    private function createProcurement(string $suffix = '001'): Procurement
    {
        $jobType = JobType::create(['name' => 'Barang HPS ' . $suffix, 'is_active' => true]);
        $qualification = Qualification::create(['name' => 'Kecil HPS ' . $suffix, 'is_active' => true]);
        $fundingSource = FundingSource::create(['name' => 'APBN HPS ' . $suffix, 'is_active' => true]);
        $method = ProcurementMethod::create(['name' => 'Tender HPS ' . $suffix, 'is_active' => true]);

        $procurementId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement', [
                'job_number'            => 'PROC-HPS-' . $suffix,
                'name'                  => 'Pengadaan Perangkat Server ' . $suffix,
                'budget_ceiling'        => 500000000,
                'estimated_price'       => 450000000,
                'planned_date'          => '2026-04-10',
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

        return Procurement::findOrFail($procurementId);
    }

    public function test_unit_seeder_and_crud_endpoints(): void
    {
        $listResp = $this->actingAsApiUser()
            ->getJson('/api/unit?all=true')
            ->assertStatus(201);

        $units = $listResp->json('result');
        $this->assertNotEmpty($units);
        $names = array_column($units, 'name');
        $this->assertContains('Unit', $names);
        $this->assertContains('Pcs', $names);
        $this->assertContains('Paket', $names);

        $created = $this->actingAsApiUser()
            ->postJson('/api/unit', [
                'name' => 'Roll Kabel',
                'code' => 'roll',
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertSame('Roll Kabel', $created['name']);
        $this->assertSame('roll', $created['code']);
        $this->assertTrue($created['is_active']);

        $this->actingAsApiUser()
            ->getJson("/api/unit/{$created['id']}")
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Roll Kabel');

        $this->actingAsApiUser()
            ->postJson("/api/unit/{$created['id']}", [
                'name'      => 'Roll Besar',
                'code'      => 'rllb',
                'is_active' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Roll Besar')
            ->assertJsonPath('result.code', 'rllb')
            ->assertJsonPath('result.is_active', false);

        $this->actingAsApiUser()
            ->deleteJson("/api/unit/{$created['id']}")
            ->assertStatus(201);

        $this->assertSoftDeleted('units', ['id' => $created['id']]);
    }

    public function test_can_create_list_show_update_and_delete_hps_items_with_tax_calculation(): void
    {
        $procurement = $this->createProcurement('CRUD-1');
        $unitUnit = Unit::where('name', 'Unit')->firstOrFail();
        $unitPaket = Unit::where('name', 'Paket')->firstOrFail();

        // Item 1: 10 Unit @ 15,000,000 dengan Pajak 11%
        // base_subtotal = 150,000,000; tax_amount = 16,500,000; subtotal = 166,500,000
        $item1 = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Server Rackmount 2U',
                'unit_id'    => $unitUnit->id,
                'quantity'   => 10,
                'unit_price' => 15000000,
                'use_tax'    => true,
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertSame('Server Rackmount 2U', $item1['name']);
        $this->assertSame((int) $unitUnit->id, $item1['unit_id']);
        $this->assertSame(10, $item1['quantity']);
        $this->assertEquals(15000000, $item1['unit_price']);
        $this->assertEquals(150000000, $item1['base_subtotal']);
        $this->assertTrue($item1['use_tax']);
        $this->assertEquals(11, $item1['tax_rate']);
        $this->assertEquals(16500000, $item1['tax_amount']);
        $this->assertEquals(166500000, $item1['subtotal']);
        $this->assertSame('Unit', $item1['unit']['name']);

        // Item 2: 1 Paket @ 20,000,000 tanpa Pajak 11%
        // base_subtotal = 20,000,000; tax_amount = 0; subtotal = 20,000,000
        $item2 = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Jasa Instalasi & Konfigurasi',
                'unit_id'    => $unitPaket->id,
                'quantity'   => 1,
                'unit_price' => 20000000,
                'use_tax'    => false,
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertFalse($item2['use_tax']);
        $this->assertEquals(0, $item2['tax_rate']);
        $this->assertEquals(0, $item2['tax_amount']);
        $this->assertEquals(20000000, $item2['subtotal']);

        // List & check summary
        $list = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/hps")
            ->assertStatus(201)
            ->json('result');

        $this->assertCount(2, $list['items']);
        $this->assertSame(2, $list['summary']['total_items']);
        $this->assertEquals(170000000, $list['summary']['total_before_tax']);
        $this->assertEquals(16500000, $list['summary']['total_tax']);
        $this->assertEquals(186500000, $list['summary']['grand_total']);

        // Ensure procurement.estimated_price was NOT overwritten
        $this->assertEquals(450000000, (float) $procurement->fresh()->estimated_price);

        // Show single item
        $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/hps/{$item1['id']}")
            ->assertStatus(201)
            ->assertJsonPath('result.id', $item1['id'])
            ->assertJsonPath('result.name', 'Server Rackmount 2U');

        // Update item 2: enable use_tax = true & quantity = 2
        // base_subtotal = 40,000,000; tax = 4,400,000; subtotal = 44,400,000
        $updatedItem2 = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps/{$item2['id']}", [
                'name'       => 'Jasa Instalasi & Training',
                'unit_id'    => $unitPaket->id,
                'quantity'   => 2,
                'unit_price' => 20000000,
                'use_tax'    => true,
            ])
            ->assertStatus(201)
            ->json('result');

        $this->assertSame('Jasa Instalasi & Training', $updatedItem2['name']);
        $this->assertSame(2, $updatedItem2['quantity']);
        $this->assertTrue($updatedItem2['use_tax']);
        $this->assertEquals(4400000, $updatedItem2['tax_amount']);
        $this->assertEquals(44400000, $updatedItem2['subtotal']);

        // Delete item 1
        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}/hps/{$item1['id']}")
            ->assertStatus(201);

        $this->assertSoftDeleted('procurement_hps_items', ['id' => $item1['id']]);

        $listAfterDelete = $this->actingAsApiUser()
            ->getJson("/api/procurement/{$procurement->id}/hps")
            ->assertStatus(201)
            ->json('result');

        $this->assertCount(1, $listAfterDelete['items']);
        $this->assertEquals(40000000, $listAfterDelete['summary']['total_before_tax']);
        $this->assertEquals(4400000, $listAfterDelete['summary']['total_tax']);
        $this->assertEquals(44400000, $listAfterDelete['summary']['grand_total']);
    }

    public function test_validates_integer_quantity_active_unit_and_cancelled_procurement(): void
    {
        $procurement = $this->createProcurement('VAL-1');
        $activeUnit = Unit::where('name', 'Pcs')->firstOrFail();
        $inactiveUnit = Unit::create([
            'name'      => 'Inactive Satuan',
            'code'      => 'inc',
            'is_active' => false,
        ]);

        // Decimal quantity must fail validation (400)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Kabel UTP',
                'unit_id'    => $activeUnit->id,
                'quantity'   => 2.5,
                'unit_price' => 100000,
                'use_tax'    => true,
            ])
            ->assertStatus(400);

        // Inactive unit must fail validation (400)
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Kabel UTP',
                'unit_id'    => $inactiveUnit->id,
                'quantity'   => 2,
                'unit_price' => 100000,
                'use_tax'    => true,
            ])
            ->assertStatus(400);

        // Create valid item before cancelling
        $item = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Switch Core',
                'unit_id'    => $activeUnit->id,
                'quantity'   => 1,
                'unit_price' => 10000000,
                'use_tax'    => true,
            ])
            ->assertStatus(201)
            ->json('result');

        // Cancel procurement
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/cancel", [
                'reason' => 'Anggaran direalokasi',
            ])
            ->assertStatus(201);

        // Cannot create, update, or delete HPS on cancelled procurement
        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Item Baru',
                'unit_id'    => $activeUnit->id,
                'quantity'   => 1,
                'unit_price' => 500000,
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cannot update cancelled procurement');

        $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps/{$item['id']}", [
                'name'       => 'Switch Core Update',
                'unit_id'    => $activeUnit->id,
                'quantity'   => 2,
                'unit_price' => 10000000,
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cannot update cancelled procurement');

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}/hps/{$item['id']}")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cannot update cancelled procurement');
    }

    public function test_enforces_hps_permissions_and_cleanup_on_delete_procurement(): void
    {
        $procurement = $this->createProcurement('PERM-1');
        $unit = Unit::where('name', 'Unit')->firstOrFail();

        $item = $this->actingAsApiUser()
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Laptop Kerja',
                'unit_id'    => $unit->id,
                'quantity'   => 5,
                'unit_price' => 12000000,
                'use_tax'    => true,
            ])
            ->assertStatus(201)
            ->json('result');

        $readPerm = Permission::where('subject', 'procurement_hps')->where('action', 'read')->firstOrFail();
        $role = Role::create(['name' => 'hps_reader', 'description' => 'Read only']);
        $role->permissions()->sync([$readPerm->id]);

        $reader = User::create([
            'name'     => 'HPS Reader User',
            'username' => 'hps_reader_user',
            'email'    => 'hps_reader@example.com',
            'password' => bcrypt('password'),
        ]);
        $reader->roles()->sync([$role->id]);

        $token = auth('api')->login($reader);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->withHeaders($headers)
            ->getJson("/api/procurement/{$procurement->id}/hps")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result.items');

        $this->withHeaders($headers)
            ->getJson("/api/procurement/{$procurement->id}/hps/{$item['id']}")
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurement->id}/hps", [
                'name'       => 'Item Forbidden',
                'unit_id'    => $unit->id,
                'quantity'   => 1,
                'unit_price' => 100000,
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->postJson("/api/procurement/{$procurement->id}/hps/{$item['id']}", [
                'name'       => 'Item Forbidden Update',
                'unit_id'    => $unit->id,
                'quantity'   => 2,
                'unit_price' => 100000,
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->deleteJson("/api/procurement/{$procurement->id}/hps/{$item['id']}")
            ->assertStatus(403);

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement/{$procurement->id}")
            ->assertStatus(201);

        $this->assertSoftDeleted('procurement_hps_items', ['id' => $item['id']]);
    }
}
