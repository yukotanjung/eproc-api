<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\EducationLevel;
use App\Models\PasswordActivate;
use App\Models\Province;
use App\Models\Tax;
use App\Models\Vendor;
use App\Models\VendorCommissioner;
use App\Models\VendorDirector;
use App\Models\VendorEquipment;
use App\Models\VendorExpert;
use App\Models\VendorFinancial;
use App\Models\VendorLegal;
use App\Models\VendorLicense;
use App\Models\VendorOwner;
use App\Models\VendorTax;
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

    public function test_vendor_and_admin_can_manage_commissioners_with_file_uploads(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '88.777.666.5-444.000',
            'company_name' => 'PT Komisaris Nusantara',
            'phone'        => '0217776665',
            'pic'          => 'Hendra Wijaya',
            'email'        => 'hendra@komisaris.co.id',
            'address'      => 'Jl. Kuningan',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'hendra@komisaris.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'hendra@komisaris.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'hendra@komisaris.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        $comm = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/commissioner', [
                'name'        => 'Hendra Wijaya',
                'npwp_number' => '01.222.333.4-555.000',
                'position'    => 'Komisaris Utama',
                'photo'       => UploadedFile::fake()->image('comm_photo.jpg'),
                'npwp'        => UploadedFile::fake()->create('comm_npwp.pdf', 100, 'application/pdf'),
                'ktp'         => UploadedFile::fake()->image('comm_ktp.png'),
            ]);

        $comm->assertStatus(201)
            ->assertJsonPath('result.name', 'Hendra Wijaya')
            ->assertJsonPath('result.position', 'Komisaris Utama');

        $commId = (int) $comm->json('result.id');
        $oldPhoto = $comm->json('result.photo_path');
        $oldNpwp = $comm->json('result.npwp_path');
        $oldKtp = $comm->json('result.ktp_path');

        $this->assertStringStartsWith('vendor/komisaris/', $oldPhoto);
        Storage::disk('local')->assertExists($oldPhoto);
        Storage::disk('local')->assertExists($oldNpwp);
        Storage::disk('local')->assertExists($oldKtp);

        // Update photo -> old photo deleted
        $updateComm = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/commissioner/{$commId}", [
                'name'        => 'Hendra Wijaya Updated',
                'npwp_number' => '01.222.333.4-555.000',
                'position'    => 'Komisaris Independen',
                'photo'       => UploadedFile::fake()->image('comm_photo_new.jpg'),
            ]);

        $updateComm->assertStatus(201)
            ->assertJsonPath('result.position', 'Komisaris Independen');

        $newPhoto = $updateComm->json('result.photo_path');
        Storage::disk('local')->assertMissing($oldPhoto);
        Storage::disk('local')->assertExists($newPhoto);

        // Download zip
        $zipResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/vendor/commissioner/{$commId}/download-zip");
        $zipResponse->assertStatus(200);
        $this->assertStringContainsString('komisaris-hendra-wijaya-updated.zip', (string) $zipResponse->headers->get('content-disposition'));

        // Admin read & delete -> all files deleted
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/commissioner?all=true")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/commissioner/{$commId}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($newPhoto);
        Storage::disk('local')->assertMissing($oldNpwp);
        Storage::disk('local')->assertMissing($oldKtp);
        $this->assertNotNull(VendorCommissioner::withTrashed()->find($commId));
    }

    public function test_vendor_and_admin_can_manage_directors_with_file_uploads(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '66.555.444.3-222.000',
            'company_name' => 'PT Direksi Nusantara',
            'phone'        => '0216665554',
            'pic'          => 'Agus Pratama',
            'email'        => 'agus@direksi.co.id',
            'address'      => 'Jl. Sudirman',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'agus@direksi.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'agus@direksi.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'agus@direksi.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        $director = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/director', [
                'name'        => 'Agus Pratama',
                'ktp_number'  => '3171000000000009',
                'npwp_number' => '01.333.444.5-666.000',
                'position'    => 'Direktur Utama',
                'is_pic'      => true,
                'photo'       => UploadedFile::fake()->image('dir_photo.jpg'),
                'npwp'        => UploadedFile::fake()->create('dir_npwp.pdf', 100, 'application/pdf'),
                'ktp'         => UploadedFile::fake()->image('dir_ktp.png'),
            ]);

        $director->assertStatus(201)
            ->assertJsonPath('result.name', 'Agus Pratama')
            ->assertJsonPath('result.position', 'Direktur Utama')
            ->assertJsonPath('result.is_pic', true);

        $directorId = (int) $director->json('result.id');
        $oldPhoto = $director->json('result.photo_path');
        $oldNpwp = $director->json('result.npwp_path');
        $oldKtp = $director->json('result.ktp_path');

        $this->assertStringStartsWith('vendor/direksi/', $oldPhoto);
        Storage::disk('local')->assertExists($oldPhoto);
        Storage::disk('local')->assertExists($oldNpwp);
        Storage::disk('local')->assertExists($oldKtp);

        // Update photo -> old photo deleted
        $updateDirector = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/director/{$directorId}", [
                'name'        => 'Agus Pratama Updated',
                'ktp_number'  => '3171000000000009',
                'npwp_number' => '01.333.444.5-666.000',
                'position'    => 'Direktur Operasional',
                'is_pic'      => false,
                'photo'       => UploadedFile::fake()->image('dir_photo_new.jpg'),
            ]);

        $updateDirector->assertStatus(201)
            ->assertJsonPath('result.position', 'Direktur Operasional')
            ->assertJsonPath('result.is_pic', false);

        $newPhoto = $updateDirector->json('result.photo_path');
        Storage::disk('local')->assertMissing($oldPhoto);
        Storage::disk('local')->assertExists($newPhoto);

        // Download zip
        $zipResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/vendor/director/{$directorId}/download-zip");
        $zipResponse->assertStatus(200);
        $this->assertStringContainsString('direksi-agus-pratama-updated.zip', (string) $zipResponse->headers->get('content-disposition'));

        // Admin read & delete -> all files deleted
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/director?all=true")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/director/{$directorId}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($newPhoto);
        Storage::disk('local')->assertMissing($oldNpwp);
        Storage::disk('local')->assertMissing($oldKtp);
        $this->assertNotNull(VendorDirector::withTrashed()->find($directorId));
    }

    public function test_vendor_and_admin_can_manage_experts_with_education_level_and_file_uploads(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();
        $educationLevel = EducationLevel::firstOrFail();

        $this->getJson('/api/vendor/education-level?all=true')
            ->assertStatus(201)
            ->assertJsonFragment(['id' => $educationLevel->id, 'name' => $educationLevel->name]);

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '55.444.333.2-111.000',
            'company_name' => 'PT Tenaga Ahli Nusantara',
            'phone'        => '0215554443',
            'pic'          => 'Rina Kartika',
            'email'        => 'rina@tenagaahli.co.id',
            'address'      => 'Jl. Asia Afrika',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'rina@tenagaahli.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'rina@tenagaahli.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'rina@tenagaahli.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        // Create expert without optional certificate
        $expert = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/expert', [
                'name'               => 'Ir. Rina Kartika',
                'birth_place'        => 'Bandung',
                'birth_date'         => '1990-05-15',
                'position'           => 'Tenaga Ahli Sipil',
                'npwp_number'        => '01.555.666.7-888.000',
                'ktp_number'         => '3273012345670001',
                'address'            => 'Jl. Dago No. 10, Bandung',
                'education_level_id' => $educationLevel->id,
                'major'              => 'Teknik Sipil',
                'diploma_number'     => 'IJZ-2013-00123',
                'experience_years'   => 8,
                'photo'              => UploadedFile::fake()->image('expert_photo.jpg'),
                'diploma'            => UploadedFile::fake()->create('expert_diploma.pdf', 100, 'application/pdf'),
            ]);

        $expert->assertStatus(201)
            ->assertJsonPath('result.name', 'Ir. Rina Kartika')
            ->assertJsonPath('result.education_level_id', $educationLevel->id)
            ->assertJsonPath('result.education_level.name', $educationLevel->name)
            ->assertJsonPath('result.certificate_path', null);

        $expertId = (int) $expert->json('result.id');
        $oldPhoto = $expert->json('result.photo_path');
        $oldDiploma = $expert->json('result.diploma_path');

        $this->assertStringStartsWith('vendor/tenaga-ahli/', $oldPhoto);
        Storage::disk('local')->assertExists($oldPhoto);
        Storage::disk('local')->assertExists($oldDiploma);

        // Update photo & add optional certificate -> old photo deleted
        $updateExpert = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/expert/{$expertId}", [
                'name'               => 'Ir. Rina Kartika M.T.',
                'birth_place'        => 'Bandung',
                'birth_date'         => '1990-05-15',
                'position'           => 'Lead Civil Engineer',
                'npwp_number'        => '01.555.666.7-888.000',
                'ktp_number'         => '3273012345670001',
                'address'            => 'Jl. Dago No. 10, Bandung',
                'education_level_id' => $educationLevel->id,
                'major'              => 'Teknik Sipil',
                'diploma_number'     => 'IJZ-2013-00123',
                'experience_years'   => 10,
                'photo'              => UploadedFile::fake()->image('expert_photo_new.jpg'),
                'certificate'        => UploadedFile::fake()->create('expert_cert.pdf', 100, 'application/pdf'),
            ]);

        $updateExpert->assertStatus(201)
            ->assertJsonPath('result.experience_years', 10);

        $newPhoto = $updateExpert->json('result.photo_path');
        $certPath = $updateExpert->json('result.certificate_path');
        Storage::disk('local')->assertMissing($oldPhoto);
        Storage::disk('local')->assertExists($newPhoto);
        Storage::disk('local')->assertExists($certPath);

        // Download zip
        $zipResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/vendor/expert/{$expertId}/download-zip");
        $zipResponse->assertStatus(200);
        $this->assertStringContainsString('tenaga-ahli-ir-rina-kartika-mt.zip', (string) $zipResponse->headers->get('content-disposition'));

        // Admin read & delete -> all files deleted
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/expert?all=true")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/expert/{$expertId}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($newPhoto);
        Storage::disk('local')->assertMissing($oldDiploma);
        Storage::disk('local')->assertMissing($certPath);
        $this->assertNotNull(VendorExpert::withTrashed()->find($expertId));
    }

    public function test_vendor_and_admin_can_manage_financial_data(): void
    {
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '44.333.222.1-000.000',
            'company_name' => 'PT Keuangan Nusantara',
            'phone'        => '0214443332',
            'pic'          => 'Surya Darma',
            'email'        => 'surya@keuangan.co.id',
            'address'      => 'Jl. Thamrin',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'surya@keuangan.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'surya@keuangan.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'surya@keuangan.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        $financial = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/vendor/financial', [
                'report_date'            => '2025-12-31',
                'fixed_assets'           => 1500000000,
                'current_assets'         => 750000000,
                'other_assets'           => 100000000,
                'long_term_liabilities'  => 300000000,
                'short_term_liabilities' => 200000000,
                'capital'                => 1000000000,
                'net_worth'              => 1850000000,
            ]);

        $financial->assertStatus(201)
            ->assertJsonPath('result.report_date', '2025-12-31')
            ->assertJsonPath('result.net_worth', 1850000000);

        $financialId = (int) $financial->json('result.id');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/vendor/financial/{$financialId}", [
                'report_date'            => '2025-12-31',
                'fixed_assets'           => 1600000000,
                'current_assets'         => 800000000,
                'other_assets'           => 100000000,
                'long_term_liabilities'  => 300000000,
                'short_term_liabilities' => 200000000,
                'capital'                => 1000000000,
                'net_worth'              => 2000000000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.net_worth', 2000000000);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vendor/financial?all=true')
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/vendor/financial/{$financialId}")
            ->assertStatus(201);

        $this->assertNotNull(VendorFinancial::withTrashed()->find($financialId));
    }

    public function test_vendor_and_admin_can_manage_tax_reports_with_file_uploads(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();
        $taxType = Tax::create([
            'name'      => 'SPT Tahunan Test',
            'is_spt'    => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/vendor/tax-type?all=true')
            ->assertStatus(201)
            ->assertJsonFragment(['id' => $taxType->id, 'name' => 'SPT Tahunan Test']);

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '33.222.111.0-999.000',
            'company_name' => 'PT Pajak Nusantara',
            'phone'        => '0213332221',
            'pic'          => 'Dian Pertiwi',
            'email'        => 'dian@pajak.co.id',
            'address'      => 'Jl. Merdeka',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'dian@pajak.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'dian@pajak.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'dian@pajak.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        $tax = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/tax', [
                'report_date'    => '2025-03-31',
                'tax_id'         => $taxType->id,
                'receipt_number' => 'BPE-2025-001',
                'receipt_file'   => UploadedFile::fake()->create('spt_2025.pdf', 100, 'application/pdf'),
            ]);

        $tax->assertStatus(201)
            ->assertJsonPath('result.tax_id', $taxType->id)
            ->assertJsonPath('result.tax.name', 'SPT Tahunan Test')
            ->assertJsonPath('result.receipt_number', 'BPE-2025-001');

        $taxId = (int) $tax->json('result.id');
        $oldPath = $tax->json('result.receipt_path');

        $this->assertStringStartsWith('vendor/pajak/', $oldPath);
        Storage::disk('local')->assertExists($oldPath);

        // Update file -> old file deleted
        $updateTax = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/tax/{$taxId}", [
                'report_date'    => '2025-04-30',
                'tax_id'         => $taxType->id,
                'receipt_number' => 'BPE-2025-002',
                'receipt_file'   => UploadedFile::fake()->create('spt_2025_rev.pdf', 120, 'application/pdf'),
            ]);

        $updateTax->assertStatus(201)
            ->assertJsonPath('result.receipt_number', 'BPE-2025-002');

        $newPath = $updateTax->json('result.receipt_path');
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        // Download zip
        $zipResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/vendor/tax/{$taxId}/download-zip");
        $zipResponse->assertStatus(200);
        $this->assertStringContainsString('pajak-bpe-2025-002.zip', (string) $zipResponse->headers->get('content-disposition'));

        // Admin read & delete -> file deleted
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/tax?all=true")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/tax/{$taxId}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($newPath);
        $this->assertNotNull(VendorTax::withTrashed()->find($taxId));
    }

    public function test_vendor_and_admin_can_manage_legal_data_with_optional_amendment(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '22.111.000.9-888.000',
            'company_name' => 'PT Hukum Nusantara',
            'phone'        => '0212221110',
            'pic'          => 'Raka Mahendra',
            'email'        => 'raka@hukum.co.id',
            'address'      => 'Jl. Kebon Sirih',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'raka@hukum.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'raka@hukum.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'raka@hukum.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        // Create without optional amendment fields
        $legal = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/legal', [
                'establishment_date'        => '2020-01-15',
                'establishment_deed_number' => 'AHU-00123',
                'establishment_notary'      => 'Notaris Andi SH',
                'establishment_deed_file'   => UploadedFile::fake()->create('akta_pendirian.pdf', 100, 'application/pdf'),
            ]);

        $legal->assertStatus(201)
            ->assertJsonPath('result.establishment_deed_number', 'AHU-00123')
            ->assertJsonPath('result.amendment_deed_path', null);

        $legalId = (int) $legal->json('result.id');
        $oldEstPath = $legal->json('result.establishment_deed_path');

        $this->assertStringStartsWith('vendor/hukum/', $oldEstPath);
        Storage::disk('local')->assertExists($oldEstPath);

        // Update with new establishment deed file + optional amendment -> old establishment file deleted
        $updateLegal = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/legal/{$legalId}", [
                'establishment_date'        => '2020-01-15',
                'establishment_deed_number' => 'AHU-00123',
                'establishment_notary'      => 'Notaris Andi SH',
                'establishment_deed_file'   => UploadedFile::fake()->create('akta_pendirian_rev.pdf', 110, 'application/pdf'),
                'amendment_date'            => '2023-06-10',
                'amendment_deed_number'     => 'AHU-00456',
                'amendment_notary'          => 'Notaris Budi SH',
                'amendment_deed_file'       => UploadedFile::fake()->create('akta_perubahan.pdf', 120, 'application/pdf'),
            ]);

        $updateLegal->assertStatus(201)
            ->assertJsonPath('result.amendment_deed_number', 'AHU-00456');

        $newEstPath = $updateLegal->json('result.establishment_deed_path');
        $amdPath = $updateLegal->json('result.amendment_deed_path');
        Storage::disk('local')->assertMissing($oldEstPath);
        Storage::disk('local')->assertExists($newEstPath);
        Storage::disk('local')->assertExists($amdPath);

        // Download zip
        $zipResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/vendor/legal/{$legalId}/download-zip");
        $zipResponse->assertStatus(200);
        $this->assertStringContainsString('hukum-ahu-00123.zip', (string) $zipResponse->headers->get('content-disposition'));

        // Admin read & delete -> all files deleted
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/legal?all=true")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/legal/{$legalId}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($newEstPath);
        Storage::disk('local')->assertMissing($amdPath);
        $this->assertNotNull(VendorLegal::withTrashed()->find($legalId));
    }

    public function test_vendor_and_admin_can_manage_licenses_with_file_uploads(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '12.345.678.9-012.000',
            'company_name' => 'PT Perizinan Nusantara',
            'phone'        => '0211234567',
            'pic'          => 'Bima Sakti',
            'email'        => 'bima@perizinan.co.id',
            'address'      => 'Jl. Medan Merdeka',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'bima@perizinan.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'bima@perizinan.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'bima@perizinan.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vendor/license/types')
            ->assertStatus(201)
            ->assertJsonPath('result', ['SIUP', 'SIUJK', 'SBU']);

        // Invalid license_type must fail
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/license', [
                'license_type'   => 'INVALID',
                'license_number' => '123',
                'issued_date'    => '2025-01-10',
                'expired_date'   => '2030-01-10',
                'issuing_agency' => 'DPMPTSP',
                'file'           => UploadedFile::fake()->create('izin.pdf', 100, 'application/pdf'),
            ])->assertStatus(400);

        $license = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/license', [
                'license_type'   => 'SIUJK',
                'license_number' => 'SIUJK-2025-001',
                'issued_date'    => '2025-01-10',
                'expired_date'   => '2030-01-10',
                'issuing_agency' => 'DPMPTSP DKI Jakarta',
                'file'           => UploadedFile::fake()->create('siujk.pdf', 100, 'application/pdf'),
            ]);

        $license->assertStatus(201)
            ->assertJsonPath('result.license_type', 'SIUJK')
            ->assertJsonPath('result.license_number', 'SIUJK-2025-001');

        $licenseId = (int) $license->json('result.id');
        $oldPath = $license->json('result.file_path');

        $this->assertStringStartsWith('vendor/perizinan/', $oldPath);
        Storage::disk('local')->assertExists($oldPath);

        // Update file -> old file deleted
        $updateLicense = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/license/{$licenseId}", [
                'license_type'   => 'SBU',
                'license_number' => 'SBU-2025-002',
                'issued_date'    => '2025-02-01',
                'expired_date'   => '2030-02-01',
                'issuing_agency' => 'LPJK',
                'file'           => UploadedFile::fake()->create('sbu.pdf', 120, 'application/pdf'),
            ]);

        $updateLicense->assertStatus(201)
            ->assertJsonPath('result.license_type', 'SBU');

        $newPath = $updateLicense->json('result.file_path');
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        // Download zip
        $zipResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get("/api/vendor/license/{$licenseId}/download-zip");
        $zipResponse->assertStatus(200);
        $this->assertStringContainsString('perizinan-sbu-2025-002.zip', (string) $zipResponse->headers->get('content-disposition'));

        // Admin read & delete -> file deleted
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/license?all=true")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/license/{$licenseId}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($newPath);
        $this->assertNotNull(VendorLicense::withTrashed()->find($licenseId));
    }

    public function test_vendor_and_admin_can_manage_equipments_with_photo_uploads(): void
    {
        Storage::fake('local');
        Notification::fake();

        $province = Province::firstOrFail();
        $city = City::where('province_id', $province->id)->firstOrFail();

        $reg = $this->postJson('/api/vendor/auth/register', [
            'npwp'         => '13.456.789.0-123.000',
            'company_name' => 'PT Peralatan Nusantara',
            'phone'        => '0217654321',
            'pic'          => 'Bagus Setiawan',
            'email'        => 'bagus@peralatan.co.id',
            'address'      => 'Jl. Industri',
            'province_id'  => $province->id,
            'city_id'      => $city->id,
            'password'     => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $vendorId = (int) $reg->json('result.vendor.id');

        $activation = PasswordActivate::where('email', 'bagus@peralatan.co.id')
            ->where('active', 1)
            ->firstOrFail();

        $this->getJson('/api/vendor/auth/verify?' . http_build_query([
            'token' => $activation->token,
            'email' => 'bagus@peralatan.co.id',
        ]))->assertStatus(201);

        $login = $this->postJson('/api/vendor/auth/login', [
            'email'    => 'bagus@peralatan.co.id',
            'password' => 'Secret123!',
        ])->assertStatus(201);

        $token = $login->json('result.token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/vendor/equipment/conditions')
            ->assertStatus(201)
            ->assertJsonPath('result', ['Baik', 'Rusak Ringan', 'Rusak Berat']);

        $equipment = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/vendor/equipment', [
                'equipment_type'   => 'Excavator',
                'brand'            => 'Komatsu',
                'quantity'         => 2,
                'capacity'         => '20 Ton',
                'manufacture_year' => 2022,
                'condition'        => 'Baik',
                'location'         => 'Gudang Bekasi',
                'photo'            => UploadedFile::fake()->image('excavator.jpg'),
            ]);

        $equipment->assertStatus(201)
            ->assertJsonPath('result.equipment_type', 'Excavator')
            ->assertJsonPath('result.condition', 'Baik');

        $equipmentId = (int) $equipment->json('result.id');
        $oldPath = $equipment->json('result.photo_path');

        $this->assertStringStartsWith('vendor/peralatan/', $oldPath);
        Storage::disk('local')->assertExists($oldPath);

        // Update photo -> old photo deleted
        $updateEquipment = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post("/api/vendor/equipment/{$equipmentId}", [
                'equipment_type'   => 'Excavator',
                'brand'            => 'Komatsu PC200',
                'quantity'         => 3,
                'capacity'         => '22 Ton',
                'manufacture_year' => 2023,
                'condition'        => 'Rusak Ringan',
                'location'         => 'Gudang Karawang',
                'photo'            => UploadedFile::fake()->image('excavator_new.jpg'),
            ]);

        $updateEquipment->assertStatus(201)
            ->assertJsonPath('result.condition', 'Rusak Ringan');

        $newPath = $updateEquipment->json('result.photo_path');
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        // Admin read & delete -> photo deleted
        $this->actingAsApiUser()
            ->getJson("/api/vendor/{$vendorId}/equipment?all=true")
            ->assertStatus(201)
            ->assertJsonCount(1, 'result');

        $this->actingAsApiUser()
            ->deleteJson("/api/vendor/{$vendorId}/equipment/{$equipmentId}")
            ->assertStatus(201);

        Storage::disk('local')->assertMissing($newPath);
        $this->assertNotNull(VendorEquipment::withTrashed()->find($equipmentId));
    }
}
