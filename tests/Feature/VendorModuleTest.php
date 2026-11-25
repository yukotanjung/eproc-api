<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\PasswordActivate;
use App\Models\Province;
use App\Models\Vendor;
use App\Models\VendorUser;
use App\Notifications\VendorEmailVerification;
use Illuminate\Support\Facades\Notification;

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
}
