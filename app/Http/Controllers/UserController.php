<?php

namespace App\Http\Controllers;

use App\Jobs\AccountActivationJob;
use App\Jobs\EmailVerificationJob;
use Exception;
use App\Models\User;
use App\Models\PasswordActivate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\RuleTrait;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = User::getUser($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            $role = count($item->roles) > 0 ? $item->roles[0] : null;
            return [
                'id'                => $item->id,
                'name'              => $item->name,
                'username'          => $item->username,
                'employee_code'     => $item->employee_code,
                'email'             => $item->email,
                'photo_url'         => $item->photo_url,
                'created_at'        => $item->created_at,
                'email_verified_at' => $item->email_verified_at,
                'role' => $role ? [
                    "id"          => $role->id,
                    "name"        => $role->name,
                    "description" => $role->description,
                ] : null,
            ];
        });
        return $this->baseResponse('Success get list user', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                'username'      => 'required|string|max:100',
                'employee_code' => 'nullable|string|max:50',
                'email'         => 'required|string|max:255',
                'photo'         => 'nullable|file',
                'callback_url'  => 'required|string|max:255',
            ]);
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);
            DB::beginTransaction();
            $datas = [
                'name'          => $request->name,
                'username'      => $request->username,
                'employee_code' => $request->employee_code,
                'email'         => $request->email,
            ];
            if ($request->has('photo')) {
                $photo = $request->file('photo');
                $photoPath = $photo->store('user/photos', 's3');
                $datas['photo'] = $photoPath;
            }
            $result = User::create($datas);
            $result->roles()->sync([$request->role_id]);
            DB::commit();
            AccountActivationJob::dispatch($result, $validated['callback_url']);
            return $this->baseResponse('Success create user', $result);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
    public function resendActivation(User $user, Request $request)
    {
        try {
            $validated = $request->validate(['callback_url' => 'required|string|max:255',]);
            AccountActivationJob::dispatch($user, $validated['callback_url']);
            return $this->baseResponse('Success resend', ["name" => $user->name]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = User::find($id);
        if ($data) {
            $role = count($data->roles) > 0 ? $data->roles[0] : null;
            $result = [
                'id'                => $data->id,
                'name'              => $data->name,
                'username'          => $data->username,
                'employee_code'     => $data->employee_code,
                'photo_url'         => $data->photo_url,
                'email'             => $data->email,
                'created_at'        => $data->created_at,
                'email_verified_at' => $data->email_verified_at,
                'role' => $role ? [
                    "id"          => $role->id,
                    "name"        => $role->name,
                    "description" => $role->description,
                ] : null,
            ];
            return $this->baseResponse('Success', $result);
        } else {
            return $this->invalidResponse("No data");
        }
    }

    public function destroy($id)
    {
        try {
            $data = User::find($id);
            if ($data) {
                DB::beginTransaction();

                $upd['active'] = 0;
                PasswordActivate::where('user_id',$id)->update($upd);

                $data->delete();

                DB::commit();
                return $this->baseResponse("Success delete user", null);
            }
            return $this->invalidResponse("No data");
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {

            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = User::find($id);
            if ($data) {

                DB::beginTransaction();

                $update = [
                    'name'          => $request->name,
                    'username'      => $request->username,
                    'employee_code' => $request->employee_code,
                ];

                if ($request->has('photo')) {
                    $photo = $request->file('photo');
                    $photoPath = $photo->store('user/photos', 's3');
                    $update['photo'] = $photoPath;
                    if ($data->photo != '') {
                        Storage::disk('s3')->delete($data->photo);
                    }
                }
                $data->update($update);
                $data->roles()->sync([$request->role_id]);
                $data->roles;

                DB::commit();

                if($request->email != $data->email){
                    EmailVerificationJob::dispatch($data, $request->name, $request->callback_url, $request->email);
                }
                return $this->baseResponse("Success update user", $data);
            }
            return $this->invalidResponse("No data");
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

            $userId = Auth::user()->id;

            $user = User::find($userId);

            if ($user) {

                if (!Hash::check($request->old_password, $user->password)) {
                    return $this->invalidResponse('The old password you entered is incorrect.');
                }

                $user->password = Hash::make($request->new_password);

                $user->update();

                DB::commit();
                return $this->baseResponse("Success update password");

            }

            return $this->invalidResponse('No account found');


        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function profile()
    {
        $user = Auth::user();
        return $this->baseResponse('Success get profile',$user);
    }

    public function updateProfile(Request $request)
    {
        try {
            $id = Auth::user()->id;

            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $user = User::find($id);
            if ($user) {

                $user->name = $request->name;
                if ($request->has('username')) {
                    $user->username = $request->username;
                }
                if ($request->has('employee_code')) {
                    $user->employee_code = $request->employee_code;
                }

                if ($request->has('photo') && $request->file('photo')->isValid() ) {
                    $photo = $request->file('photo');
                    $photoPath = $photo->store('user/photos', 's3');

                    $user->photo = $photoPath;
                }

                $user->update();


                if ($request->email != $user->email) {
                    EmailVerificationJob::dispatch($user, $request->name, $request->callback_url, $request->email);
                }

                DB::commit();
                return $this->baseResponse("Success update profile", $user);
            }

            return $this->invalidResponse("Account not found");

        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function updateNotificationDevice(Request $request)
    {
        try {
            $id = Auth::user()->id;

            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $user = User::find($id);
            if ($user) {

                $user->notification_device = $request->notification_device;

                $user->update();

                DB::commit();
                return $this->baseResponse("Success update notification", $user);
            }

            return $this->invalidResponse("Account not found");

        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }


}
