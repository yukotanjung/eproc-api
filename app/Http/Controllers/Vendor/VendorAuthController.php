<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\PasswordActivate;
use App\Models\PasswordReset;
use App\Models\Vendor;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class VendorAuthController extends Controller
{
    use RuleTrait;

    public function register(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            if ($request->filled('province_id') && $request->filled('city_id')) {
                $cityExists = City::where('id', $request->city_id)
                    ->where('province_id', $request->province_id)
                    ->exists();
                if (!$cityExists) {
                    return $this->errorResponses('Kota yang dipilih tidak sesuai dengan provinsi yang dipilih');
                }
            }

            DB::beginTransaction();

            $vendor = Vendor::create([
                'npwp'         => $request->npwp,
                'company_name' => $request->company_name,
                'phone'        => $request->phone,
                'pic'          => $request->pic,
                'email'        => $request->email,
                'address'      => $request->address,
                'province_id'  => $request->province_id,
                'city_id'      => $request->city_id,
                'is_active'    => true,
            ]);

            $user = VendorUser::create([
                'vendor_id'         => $vendor->id,
                'name'              => $request->filled('name') ? $request->name : $request->pic,
                'username'          => $request->username,
                'email'             => $request->filled('user_email') ? $request->user_email : $request->email,
                'phone'             => $request->filled('user_phone') ? $request->user_phone : $request->phone,
                'password'          => Hash::make($request->password),
                'is_active'         => false,
                'email_verified_at' => null,
            ]);

            DB::commit();

            $user->sendVerificationEmail($request->callback_url);

            $vendor->load(['province', 'city']);

            return $this->baseResponse('Registrasi vendor berhasil. Silakan cek email Anda untuk verifikasi akun.', [
                'vendor' => $vendor->toApiArray(),
                'user'   => $user->toApiArray(),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function verify(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $query = PasswordActivate::query()
                ->where('token', '=', $request->token)
                ->where('active', '=', 1);

            if ($request->filled('email')) {
                $query->where('email', '=', $request->email);
            }

            $activation = $query->first();
            if (!$activation) {
                return $this->invalidResponse('Link verifikasi tidak valid atau sudah kedaluwarsa', 400);
            }

            $user = VendorUser::query()
                ->when($activation->user_id, fn ($q) => $q->where('id', $activation->user_id), fn ($q) => $q->where('email', $activation->email))
                ->first();

            if (!$user) {
                return $this->invalidResponse('Akun tidak ditemukan', 404);
            }

            DB::beginTransaction();

            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();

            PasswordActivate::query()
                ->where('email', '=', $activation->email)
                ->where('active', '=', 1)
                ->update(['active' => 0]);

            DB::commit();

            return $this->baseResponse('Akun berhasil diverifikasi. Anda sekarang dapat login.', $user->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function resendVerification(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $user = VendorUser::query()
                ->where('email', '=', $request->email)
                ->first();

            if (!$user) {
                return $this->invalidResponse('Akun tidak ditemukan', 404);
            }

            if ($user->email_verified_at !== null && $user->is_active) {
                return $this->invalidResponse('Akun sudah diverifikasi', 400);
            }

            $user->sendVerificationEmail($request->callback_url);

            return $this->baseResponse('Email verifikasi berhasil dikirim', ['email' => $user->email]);
        } catch (Exception $e) {
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function login(Request $request)
    {
        $loginInput = $request->input('login') ?? $request->input('email') ?? $request->input('username');
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $loginInput, 'password' => $request->password];

        try {
            if (!($token = auth('vendor')->attempt($credentials))) {
                return $this->invalidResponse('Email/username atau password salah', 400);
            }
        } catch (JWTException $e) {
            return $this->invalidResponse('Gagal membuat token', 500);
        }

        /** @var VendorUser $user */
        $user = auth('vendor')->user();
        $user->load('vendor.province', 'vendor.city');

        if ($user->email_verified_at === null) {
            auth('vendor')->logout();
            return $this->invalidResponse('Akun belum diverifikasi. Silakan cek email Anda untuk link verifikasi.', 403);
        }

        if (!$user->is_active || ($user->vendor && !$user->vendor->is_active)) {
            auth('vendor')->logout();
            return $this->invalidResponse('Akun tidak aktif', 403);
        }

        $user->last_login = now();
        $user->save();

        $result = [
            'id'         => (int) $user->id,
            'vendor_id'  => (int) $user->vendor_id,
            'name'       => $user->name,
            'username'   => $user->username,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'photo_url'  => $user->photo_url,
            'is_active'  => (bool) $user->is_active,
            'last_login' => $user->last_login,
            'token'      => $token,
            'vendor'     => $user->vendor ? $user->vendor->toApiArray() : null,
        ];

        return $this->baseResponse('Login berhasil', $result);
    }

    public function me()
    {
        /** @var VendorUser $user */
        $user = auth('vendor')->user();
        $user->load('vendor.province', 'vendor.city');

        $result = [
            'id'         => (int) $user->id,
            'vendor_id'  => (int) $user->vendor_id,
            'name'       => $user->name,
            'username'   => $user->username,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'photo_url'  => $user->photo_url,
            'is_active'  => (bool) $user->is_active,
            'last_login' => $user->last_login,
            'vendor'     => $user->vendor ? $user->vendor->toApiArray() : null,
        ];

        return $this->baseResponse('Profil pengguna', $result);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return $this->baseResponse('Logout berhasil', null);
    }

    public function sendForgotPasswordLink(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $user = VendorUser::query()
                ->whereNull('deleted_at')
                ->where('email', '=', $request->email)
                ->first();

            if ($user) {
                if ($user->email_verified_at == null) {
                    return $this->invalidResponse('Anda harus memverifikasi akun terlebih dahulu. Cek email Anda untuk link verifikasi atau hubungi admin.');
                }

                $response = $user->sendPasswordReset($request->callback_url);
                return $this->baseResponse('Email berhasil dikirim', $response);
            }

            return $this->invalidResponse('Gagal mengirim email. Email tidak ditemukan.');
        } catch (Exception $e) {
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function checkForgotPasswordToken(Request $request)
    {
        try {
            $email = Crypt::decrypt($request->email);
            $token = $request->token;
            $getToken = PasswordReset::query()
                ->where('email', '=', $email)
                ->where('active', '=', 1)
                ->first();
            if (!$getToken) {
                return $this->invalidResponse('Token sudah kedaluwarsa');
            }
            $checkHashToken = md5($getToken->token) == $token;
            if ($checkHashToken) {
                return $this->baseResponse('Token valid', ['token' => $token]);
            }
            return $this->invalidResponse('Token tidak valid');
        } catch (DecryptException $e) {
            return $this->invalidResponse('Email tidak valid');
        }
    }

    public function updateForgotPassword(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $email = Crypt::decrypt($request->email);
            $token = $request->token;
            $user = VendorUser::query()->where('email', '=', $email)->first();
            if (!$user) {
                return $this->invalidResponse('Akun tidak ditemukan');
            }

            $passwordForgot = PasswordReset::query()
                ->where('email', '=', $email)
                ->where('active', '=', 1)
                ->first();
            if (!$passwordForgot) {
                return $this->invalidResponse('Token sudah kedaluwarsa');
            }
            $checkHashToken = md5($passwordForgot->token) == $token;
            if (!$checkHashToken) {
                return $this->invalidResponse('Token tidak valid');
            }

            $update = $user->update([
                'password' => Hash::make($request->password),
            ]);

            PasswordReset::query()
                ->where('email', '=', $email)
                ->where('active', '=', 1)
                ->update(['active' => 0]);

            DB::commit();
            return $this->baseResponse('Password berhasil diperbarui', $update);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
