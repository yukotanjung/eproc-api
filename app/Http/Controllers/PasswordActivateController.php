<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Traits\RuleTrait;
use Illuminate\Http\Request;
use App\Models\PasswordActivate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PasswordActivateController extends Controller
{
    use RuleTrait;

    public function activateAccount(Request $request)
    {

        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $email = Crypt::decrypt($request->email);
            $token = $request->token;

            $passwordActivation = PasswordActivate::query()->where('email', '=', $email)
                ->where('active', '=', 1)
                ->first();
            if (!$passwordActivation) return $this->errorResponses("Token aktivasi sudah kedaluwarsa.");
            $checkHashToken = md5($passwordActivation->token) == $token;
            if (!$checkHashToken) return $this->errorResponses("Token aktivasi tidak valid.");

            $user = User::query()->where('email', "=", $email)->first();
            $user->password = Hash::make($request->password);
            $user->email_verified_at = date("Y-m-d H:i:s");
            $update = $user->update();

            if ($update) {
                $passwordActivation = PasswordActivate::query()->where('email', '=', $email)
                    ->where('active', '=', 1)->update(["active" => 0]);
                return $this->baseResponse('Account has been activated',$user);
            } else {
                return $this->invalidResponse("Gagal mengaktifkan akun.");
            }
        } catch (Exception $e) {
            return $this->failedActionResponse($e, "Gagal mengaktifkan akun.");
        }
    }

    public function verifyEmail(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $email = Crypt::decrypt($request->email);
            $token = $request->token;
            $uid = Crypt::decrypt($request->uid);

            $passwordActivation = PasswordActivate::query()->where('email', '=', $email)
                ->where('active', '=', 1)
                ->first();

            if (!$passwordActivation) return $this->errorResponses("Token verifikasi sudah kedaluwarsa.");
            $checkHashToken = md5($passwordActivation->token) == $token;
            if (!$checkHashToken) return $this->errorResponses("Token verifikasi tidak valid.");

            $user = User::find($uid);

            $user->email = $email;
            $update = $user->update();

            $passwordActivation = PasswordActivate::query()->where('email', '=', $email)
                    ->where('active', '=', 1)->update(["active" => 0]);

            DB::commit();
            return $this->baseResponse('Email has been changed',$user);

        } catch (Exception $e) {
            return $this->failedActionResponse($e, "Gagal memverifikasi email.");
        }
    }

    public function checkToken(Request $request)
    {
        $email = Crypt::decrypt($request->email);
        $token = $request->token;

        $passwordActivation = PasswordActivate::query()->where('email', '=', $email)
            ->where('active', '=', 1)
            ->first();

        if (!$passwordActivation) {
            return $this->errorResponses("Token aktivasi sudah kedaluwarsa.");
        }
        $checkHashToken = md5($passwordActivation->token) == $token;
        if ($checkHashToken)
                return $this->baseResponse("Token is available",["token" => $token]);
            else
                return $this->invalidResponse("Token aktivasi tidak valid.");
    }
}
