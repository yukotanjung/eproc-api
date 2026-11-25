<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\PasswordActivate;
use App\Models\Vendor;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VendorController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = Vendor::getVendor($request, $page, $pageSize, $all);

        $result = $data->map(fn (Vendor $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar vendor', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
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
                'is_active'    => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
            ]);

            if ($request->boolean('create_initial_user', true)) {
                $userEmail = $request->filled('user_email') ? $request->user_email : $request->email;
                $userName = $request->filled('user_name') ? $request->user_name : $request->pic;
                $userData = [
                    'vendor_id' => $vendor->id,
                    'name'      => $userName,
                    'username'  => $request->input('username'),
                    'email'     => $userEmail,
                    'phone'     => $request->filled('user_phone') ? $request->user_phone : $request->phone,
                    'is_active' => true,
                ];
                if ($request->filled('password')) {
                    $userData['password'] = Hash::make($request->password);
                    $userData['email_verified_at'] = now();
                }
                $vendorUser = VendorUser::create($userData);
                if (!$request->filled('password')) {
                    $vendorUser->sendVerificationEmail($request->callback_url);
                }
            }

            DB::commit();

            $vendor->load(['province', 'city', 'users']);
            return $this->baseResponse('Vendor berhasil dibuat', $vendor->toApiArray(true));
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = Vendor::with(['province', 'city', 'users'])->withCount('users')->find($id);
        if ($data) {
            return $this->baseResponse('Berhasil mengambil detail vendor', $data->toApiArray(true));
        }
        return $this->invalidResponse('Data tidak ditemukan');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = Vendor::find($id);
            if (!$data) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $provinceId = $request->has('province_id') ? $request->province_id : $data->province_id;
            $cityId = $request->has('city_id') ? $request->city_id : $data->city_id;
            if (!empty($provinceId) && !empty($cityId)) {
                $cityExists = City::where('id', $cityId)
                    ->where('province_id', $provinceId)
                    ->exists();
                if (!$cityExists) {
                    return $this->errorResponses('Kota yang dipilih tidak sesuai dengan provinsi yang dipilih');
                }
            }

            DB::beginTransaction();

            $data->update([
                'npwp'         => $request->npwp,
                'company_name' => $request->company_name,
                'phone'        => $request->has('phone') ? $request->phone : $data->phone,
                'pic'          => $request->pic,
                'email'        => $request->email,
                'address'      => $request->has('address') ? $request->address : $data->address,
                'province_id'  => $provinceId,
                'city_id'      => $cityId,
                'is_active'    => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
            ]);

            DB::commit();

            $data->load(['province', 'city', 'users']);
            return $this->baseResponse('Vendor berhasil diperbarui', $data->toApiArray(true));
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $data = Vendor::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->users()->delete();
                $data->delete();

                DB::commit();
                return $this->baseResponse('Vendor berhasil dihapus', null);
            }
            return $this->invalidResponse('Data tidak ditemukan');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function indexUsers(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }

        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorUser::getVendorUser($request, $page, $pageSize, $all, $vendor->id);

        $result = $data->map(fn (VendorUser $u) => $u->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar user vendor', $result, !$all ? $this->pagination($data) : null);
    }

    public function storeUser(Request $request, $vendorId)
    {
        try {
            $vendor = Vendor::find($vendorId);
            if (!$vendor) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $payload = [
                'vendor_id' => $vendor->id,
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

    public function showUser($vendorId, $userId)
    {
        $user = VendorUser::where('vendor_id', $vendorId)->find($userId);
        if ($user) {
            return $this->baseResponse('Berhasil mengambil detail user vendor', $user->toApiArray());
        }
        return $this->invalidResponse('Data tidak ditemukan');
    }

    public function updateUser(Request $request, $vendorId, $userId)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $userId])) return $this->errorResponses($input_error);

            $user = VendorUser::where('vendor_id', $vendorId)->find($userId);
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

    public function destroyUser($vendorId, $userId)
    {
        try {
            $user = VendorUser::where('vendor_id', $vendorId)->find($userId);
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

    public function resendActivation($vendorId, $userId, Request $request)
    {
        try {
            $user = VendorUser::where('vendor_id', $vendorId)->find($userId);
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
