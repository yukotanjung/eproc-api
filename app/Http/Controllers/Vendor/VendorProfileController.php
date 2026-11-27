<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\PasswordActivate;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class VendorProfileController extends Controller
{
    use RuleTrait;

    public function profile()
    {
        /** @var VendorUser $user */
        $user = auth('vendor')->user();
        $user->load('vendor.province', 'vendor.city');

        return $this->baseResponse('Berhasil mengambil profil', [
            'user'   => $user->toApiArray(),
            'vendor' => $user->vendor ? $user->vendor->toApiArray() : null,
        ]);
    }

    public function updateProfile(Request $request)
    {
        try {
            /** @var VendorUser $user */
            $user = auth('vendor')->user();
            $id = $user->id;

            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $user->name = $request->name;
            if ($request->has('username')) {
                $user->username = $request->username;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }

            if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                $photo = $request->file('photo');
                $photoPath = $photo->store('vendor/photos', 's3');
                if (!empty($user->photo)) {
                    Storage::disk('s3')->delete($user->photo);
                }
                $user->photo = $photoPath;
            }

            $oldEmail = $user->email;
            if ($request->filled('email') && $request->email !== $oldEmail) {
                $user->email = $request->email;
                $user->email_verified_at = null;
                $user->is_active = false;
                $user->save();
                $user->sendVerificationEmail($request->callback_url);
            } else {
                $user->save();
            }

            DB::commit();
            return $this->baseResponse('Profil berhasil diperbarui', $user->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            /** @var VendorUser $user */
            $user = auth('vendor')->user();

            if (!Hash::check($request->old_password, $user->password)) {
                return $this->invalidResponse('Password lama yang Anda masukkan salah.');
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            DB::commit();
            return $this->baseResponse('Password berhasil diperbarui');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function company()
    {
        /** @var VendorUser $user */
        $user = auth('vendor')->user();
        $vendor = $user->vendor()->with(['province', 'city'])->withCount('users')->first();
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil data perusahaan vendor', $vendor->toApiArray());
    }

    public function updateCompany(Request $request)
    {
        try {
            /** @var VendorUser $user */
            $user = auth('vendor')->user();
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            if ($input_error = $this->check_input_validation(['own_id' => $vendor->id])) return $this->errorResponses($input_error);

            $provinceId = $request->has('province_id') ? $request->province_id : $vendor->province_id;
            $cityId = $request->has('city_id') ? $request->city_id : $vendor->city_id;
            if (!empty($provinceId) && !empty($cityId)) {
                $cityExists = City::where('id', $cityId)
                    ->where('province_id', $provinceId)
                    ->exists();
                if (!$cityExists) {
                    return $this->errorResponses('Kota yang dipilih tidak sesuai dengan provinsi yang dipilih');
                }
            }

            DB::beginTransaction();

            $vendor->update([
                'npwp'         => $request->npwp,
                'company_name' => $request->company_name,
                'phone'        => $request->has('phone') ? $request->phone : $vendor->phone,
                'pic'          => $request->pic,
                'email'        => $request->email,
                'address'      => $request->has('address') ? $request->address : $vendor->address,
                'province_id'  => $provinceId,
                'city_id'      => $cityId,
            ]);

            DB::commit();

            $vendor->load(['province', 'city']);
            return $this->baseResponse('Data perusahaan vendor berhasil diperbarui', $vendor->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function indexUsers(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorUser::getVendorUser($request, $page, $pageSize, $all, $authUser->vendor_id);

        $result = $data->map(fn (VendorUser $u) => $u->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar user vendor', $result, !$all ? $this->pagination($data) : null);
    }

    public function storeUser(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            /** @var VendorUser $authUser */
            $authUser = auth('vendor')->user();

            DB::beginTransaction();

            $payload = [
                'vendor_id' => $authUser->vendor_id,
                'name'      => $request->name,
                'username'  => $request->username,
                'email'     => $request->email,
                'phone'     => $request->phone,
                'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
            ];

            if ($request->filled('password')) {
                $payload['password'] = Hash::make($request->password);
                $payload['email_verified_at'] = now();
            }

            $user = VendorUser::create($payload);

            DB::commit();

            if (!$request->filled('password')) {
                $user->sendVerificationEmail($request->callback_url);
            }

            return $this->baseResponse('User vendor berhasil dibuat', $user->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function showUser($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();
        $user = VendorUser::where('vendor_id', $authUser->vendor_id)->find($id);
        if ($user) {
            return $this->baseResponse('Berhasil mengambil detail user vendor', $user->toApiArray());
        }
        return $this->invalidResponse('Data tidak ditemukan');
    }

    public function updateUser(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            /** @var VendorUser $authUser */
            $authUser = auth('vendor')->user();
            $user = VendorUser::where('vendor_id', $authUser->vendor_id)->find($id);
            if (!$user) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            DB::beginTransaction();

            $payload = [
                'name'      => $request->name,
                'username'  => $request->has('username') ? $request->username : $user->username,
                'email'     => $request->email,
                'phone'     => $request->has('phone') ? $request->phone : $user->phone,
                'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $user->is_active,
            ];

            if ($request->filled('password')) {
                $payload['password'] = Hash::make($request->password);
            }

            $user->update($payload);

            DB::commit();
            return $this->baseResponse('User vendor berhasil diperbarui', $user->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroyUser($id)
    {
        try {
            /** @var VendorUser $authUser */
            $authUser = auth('vendor')->user();
            if ((int) $authUser->id === (int) $id) {
                return $this->invalidResponse('Tidak dapat menghapus akun sendiri yang sedang aktif');
            }

            $user = VendorUser::where('vendor_id', $authUser->vendor_id)->find($id);
            if ($user) {
                DB::beginTransaction();

                PasswordActivate::where('user_id', (string) $user->id)->update(['active' => 0]);
                $user->delete();

                DB::commit();
                return $this->baseResponse('User vendor berhasil dihapus', null);
            }
            return $this->invalidResponse('Data tidak ditemukan');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function resendActivation($id, Request $request)
    {
        try {
            /** @var VendorUser $authUser */
            $authUser = auth('vendor')->user();
            $user = VendorUser::where('vendor_id', $authUser->vendor_id)->find($id);
            if (!$user) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $user->sendVerificationEmail($request->callback_url);
            return $this->baseResponse('Email verifikasi berhasil dikirim ulang', ['name' => $user->name]);
        } catch (Exception $e) {
            return $this->invalidResponse($e->getMessage());
        }
    }
}
