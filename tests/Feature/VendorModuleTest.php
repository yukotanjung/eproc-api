<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\PasswordActivate;
use App\Models\Province;
use App\Models\Vendor;
use App\Models\VendorOwner;
use App\Models\VendorUser;
use App\Notifications\VendorEmailVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class VendorModuleTest extends ApiTestCase
{
    public function test_admin_can_crud_province_and_city_and_filter_city_by_province(): void
    {
        $createProv = $this->actingAsApiUser()->postJson('/api/province', [
            'name' => 'Provinsi Test Baru',
        ]);

        $createProv->assertStatus(201)
            ->assertJsonPath('result.name', 'Provinsi Test Baru')
            ->assertJsonPath('result.is_active', true);

        $provinceId = (int) $createProv->json('result.id');

        $createCity = $this->actingAsApiUser()->postJson('/api/city', [
            'province_id' => $provinceId,
            'name'        => 'Kota Test Baru',
        ]);

        $createCity->assertStatus(201)
            ->assertJsonPath('result.province_id', $provinceId)
            ->assertJsonPath('result.name', 'Kota Test Baru');

        $cityId = (int) $createCity->json('result.id');

        $this->actingAsApiUser()
            ->getJson("/api/city?province_id={$provinceId}&all=true")
            ->assertStatus(201)
            ->assertJsonFragment(['id' => $cityId, 'name' => 'Kota Test Baru']);

        $this->getJson("/api/vendor/city?province_id={$provinceId}&all=true")
            ->assertStatus(201)
            ->assertJsonFragment(['id' => $cityId, 'name' => 'Kota Test Baru']);

        $this->actingAsApiUser()
            ->postJson("/api/city/{$cityId}", [
                'province_id' => $provinceId,
                'name'        => 'Kota Test Revisi',
                'is_active'   => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Kota Test Revisi')
            ->assertJsonPath('result.is_active', false);

        $this->actingAsApiUser()
            ->deleteJson("/api/city/{$cityId}")
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->deleteJson("/api/province/{$provinceId}")
            ->assertStatus(201);

        $this->assertNotNull(City::withTrashed()->find($cityId));
        $this->assertNotNull(Province::withTrashed()->find($provinceId));
    }

    public function test_vendor_register_sends_verification_link_and_click_activates_for_login(): void
    {
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '99.888.777.6-555.000',
            'company_name' => 'PT Sinergi Karya Nusantara',
            'phone'        => '0217778889',
            'pic'          => 'Andi Wijaya',
            'email'        => 'andi@sinergikarya.co.id',
            'address'      => 'Jl. Gatot Subroto Kav. 10',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'name'         => null,
            'username'     => 'andiwijaya',
            'user_email'   => null,
            'user_phone'   => null,
            'password'     => 'Secret123!',
        ]);

        $reg->assertStatus(201)
            ->assertJsonPath('result.vendor.npwp', '99.888.777.6-555.000')
            ->assertJsonPath('result.vendor.company_name', 'PT Sinergi Karya Nusantara')
            ->assertJsonPath('result.user.email', 'andi@sinergikarya.co.id')
            ->assertJsonPath('result.user.is_active', false);

        $vendorId = (int) $reg->json('result.vendor.id');
        $firstUserId = (int) $reg->json('result.user.id');
        $user = VendorUser::findOrFail($firstUserId);

        Notification::assertSentTo($user, VendorEmailVerification::class);

        // Login before verification must be rejected
        $this->postJson('/api/vendor/auth/login', [
            'email'    => 'andi@sinergikarya.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(403);

        // Resend verification email
        $this->postJson('/api/vendor/auth/resend-verification', [
            'email' => 'andi@sinergikarya.co.id',
        ])->assertStatus(201);

        $activation = PasswordActivate::where('email', 'andi@sinergikarya.co.id')
            ->where('active', 1)
            ->firstOrFail();

        // Click verification link (GET) -> directly activates account
        $verify = $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'andi@sinergikarya.co.id',
        ]));

        $verify->assertStatus(201)
            ->assertJsonPath('result.is_active', true);

        $this->assertNotNull($verify->json('result.email_verified_at'));

        // Login succeeds after verification
        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'andi@sinergikarya.co.id',
            'password' => 'Secret123!',
        ]);

        $login->assertStatus(201)
            ->assertJsonPath('result.id', $firstUserId)
            ->assertJsonPath('result.vendor_id', $vendorId);

        $token = $login->json('result.token');
        $this->assertNotEmpty($token);
        $this->assertNotNull($login->json('result.last_login'));

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vendor/auth/me')
            ->assertStatus(201)
            ->assertJsonPath('result.email', 'andi@sinergikarya.co.id');

        $addSecondUser = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/vendor/user', [
                'name'      => 'Siti Rahma',
                'username'  => 'sitirahma',
                'email'     => 'siti@sinergikarya.co.id',
                'phone'     => '081299988877',
                'password'  => 'Secret456!',
                'is_active' => true,
            ]);

        $addSecondUser->assertStatus(201)
            ->assertJsonPath('result.vendor_id', $vendorId)
            ->assertJsonPath('result.email', 'siti@sinergikarya.co.id');

        $secondUserId = (int) $addSecondUser->json('result.id');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vendor/user?all=true')
            ->assertStatus(201)
            ->assertJsonCount(2, 'result');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/vendor/user/{$secondUserId}", [
                'name'      => 'Siti Rahma Update',
                'email'     => 'siti@sinergikarya.co.id',
                'is_active' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.is_active', false);

        auth()->forgetGuards();

        $this->postJson('/api/vendor/auth/login', [
            'email'    => 'siti@sinergikarya.co.id',
            'password' => 'Secret456!',
        ])->assertStatus(403);
    }

    public function test_admin_can_manage_vendor_and_delete(): void
    {
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $createVendor = $this->actingAsApiUser()->postJson('/api/vendor', [
            'npwp'         => '11.222.333.4-555.000',
            'company_name' => 'PT Admin Created Vendor',
            'phone'        => '0213334445',
            'pic'          => 'Rudi Hartono',
            'email'        => 'rudi@adminvendor.co.id',
            'address'      => 'Jl. Thamrin No. 1',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'AdminSet123!',
        ]);

        $createVendor->assertStatus(201)
            ->assertJsonPath('result.npwp', '11.222.333.4-555.000');

        $vendorId = (int) $createVendor->json('result.id');

        $this->postJson('/api/vendor/auth/login', [
            'email'    => 'rudi@adminvendor.co.id',
            'password' => 'AdminSet123!',
        ])->assertStatus(201);

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}")
            ->assertStatus(201);

        $this->assertNotNull(Vendor::withTrashed()->find($vendorId));
    }

    public function test_vendor_and_admin_can_manage_multiple_owners_with_file_uploads(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '77.666.555.4-333.000',
            'company_name' => 'PT Pemilik Banyak Tbk',
            'phone'        => '0218889990',
            'pic'          => 'Dewi Lestari',
            'email'        => 'dewi@pemilikbanyak.co.id',
            'address'      => 'Jl. Rasuna Said',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'dewi@pemilikbanyak.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'dewi@pemilikbanyak.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'dewi@pemilikbanyak.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        // Owner 1 (60%)
        $owner1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/owner', [
                'name'        => 'Dewi Lestari',
                'ktp_number'  => '3171000000000001',
                'npwp_number' => '01.111.222.3-444.000',
                'address'     => 'Jakarta Selatan',
                'percentage'  => 60,
                'photo'       => UploadedFile::fake()->image('photo1.jpg'),
                'npwp'        => UploadedFile::fake()->create('npwp1.pdf', 100, 'application/pdf'),
                'ktp'         => UploadedFile::fake()->image('ktp1.png'),
            ]);

        $owner1->assertStatus(201)
            ->assertJsonPath('result.name', 'Dewi Lestari')
            ->assertJsonPath('result.percentage', 60);

        $photoPath1 = $owner1->json('result.photo_path');
        $npwpPath1 = $owner1->json('result.npwp_path');
        $ktpPath1 = $owner1->json('result.ktp_path');

        $this->assertStringStartsWith('vendor/pemilik/', $photoPath1);
        $this->assertStringStartsWith('vendor/pemilik/', $npwpPath1);
        $this->assertStringStartsWith('vendor/pemilik/', $ktpPath1);
        Storage::disk('local')->assertExists($photoPath1);
        Storage::disk('local')->assertExists($npwpPath1);
        Storage::disk('local')->assertExists($ktpPath1);

        // Owner 2 (50% -> exceeds 100% total, must fail)
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/owner', [
                'name'        => 'Bambang',
                'ktp_number'  => '3171000000000002',
                'npwp_number' => '02.111.222.3-444.000',
                'address'     => 'Bandung',
                'percentage'  => 50,
                'photo'       => UploadedFile::fake()->image('photo2.jpg'),
                'npwp'        => UploadedFile::fake()->image('npwp2.jpg'),
                'ktp'         => UploadedFile::fake()->image('ktp2.jpg'),
            ])->assertStatus(400);

        // Owner 2 (40% -> valid)
        $owner2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/owner', [
                'name'        => 'Bambang',
                'ktp_number'  => '3171000000000002',
                'npwp_number' => '02.111.222.3-444.000',
                'address'     => 'Bandung',
                'percentage'  => 40,
                'photo'       => UploadedFile::fake()->image('photo2.jpg'),
                'npwp'        => UploadedFile::fake()->image('npwp2.jpg'),
                'ktp'         => UploadedFile::fake()->image('ktp2.jpg'),
            ])->assertStatus(201);

        $owner2Id = (int) $owner2->json('result.id');
        $owner2OldPhoto = $owner2->json('result.photo_path');
        $owner2OldNpwp = $owner2->json('result.npwp_path');
        $owner2OldKtp = $owner2->json('result.ktp_path');

        // Update owner2 photo & ktp -> old photo & ktp must be deleted, npwp stays
        $updateOwner2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/owner/{$owner2Id}", [
                'name'        => 'Bambang Updated',
                'ktp_number'  => '3171000000000002',
                'npwp_number' => '02.111.222.3-444.000',
                'address'     => 'Bandung Baru',
                'percentage'  => 40,
                'photo'       => UploadedFile::fake()->image('photo2_new.jpg'),
                'ktp'         => UploadedFile::fake()->image('ktp2_new.jpg'),
            ]);

        $updateOwner2->assertStatus(201)
            ->assertJsonPath('result.name', 'Bambang Updated');

        $owner2NewPhoto = $updateOwner2->json('result.photo_path');
        $owner2NewKtp = $updateOwner2->json('result.ktp_path');

        Storage::disk('local')->assertMissing($owner2OldPhoto);
        Storage::disk('local')->assertMissing($owner2OldKtp);
        Storage::disk('local')->assertExists($owner2NewPhoto);
        Storage::disk('local')->assertExists($owner2NewKtp);
        Storage::disk('local')->assertExists($owner2OldNpwp);

        // Download owner2 files as zip
        $zipResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/vendor/owner/{$owner2Id}/download-zip");
        $zipResponse->assertStatus(200);
        $this->assertStringContainsString('pemilik-bambang-updated.zip', (string) $zipResponse->headers->get('content-disposition'));

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vendor/owner?all=true')
            ->assertStatus(201)
            ->assertJsonCount(2, 'result');

        // Admin can also read & delete owner -> all files must be deleted from storage
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/owner?all=true")
            ->assertStatus(201)
            ->assertJsonCount(2, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/owner/{$owner2Id}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($owner2NewPhoto);
        Storage::disk('local')->assertMissing($owner2NewKtp);
        Storage::disk('local')->assertMissing($owner2OldNpwp);
        $this->assertNotNull(VendorOwner::withTrashed()->find($owner2Id));
    }
}
