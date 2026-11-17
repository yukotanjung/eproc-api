<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordReset;
use App\Traits\RuleTrait;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PasswordForgotController extends Controller
{
    use RuleTrait;

    public function sendLink(Request $request)
    {
        try {

            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $user = User::query()
            ->whereNull("deleted_at")
            ->where("email", "=", $request->email)
            ->first();

            if ($user) {
                if ($user->email_verified_at == null)
                return $this->invalidResponse("Akun Anda belum diaktifkan. Silakan periksa email Anda untuk tautan aktivasi atau hubungi admin.");

                $response = $user->sendPasswordReset($request->callback_url);

                return $this->baseResponse("Email sent",$response);
            }
            else {
                return $this->invalidResponse("Gagal mengirim email. Alamat email tidak ditemukan.");
            }

        } catch (Exception $e) {

            return $this->failedActionResponse($e, "Gagal mengirim tautan atur ulang kata sandi.");
       }
    }

    public function checkToken(Request $request)
    {
        try{
            $email = Crypt::decrypt($request->email);
            $token = $request->token;
            $getToken = PasswordReset::query()
                ->where('email', '=', $email)
                ->where('active', '=', 1)
                ->first();
            if (!$getToken)
                return $this->invalidResponse("Token sudah kedaluwarsa.");
            $checkHashToken = md5($getToken->token) == $token;
            if ($checkHashToken)
                return $this->baseResponse("Token is available",["token" => $token]);
            else
                return $this->invalidResponse("Token tidak valid.");
        }

        catch(DecryptException $e){
            return $this->invalidResponse("Alamat email tidak valid.");
        }
    }

    public function updatePassword(Request $request)
    {
        try {

            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $email = Crypt::decrypt($request->email);
            $token = $request->token;
            $user = User::query()->where('email', "=", $email)->first();

            $passwordForgot = PasswordReset::query()->where('email', '=', $email)
                ->where("active", "=", 1)
                ->first();
            if (!$passwordForgot){
                return $this->invalidResponse("Token sudah kedaluwarsa.");
            }
            $checkHashToken = md5($passwordForgot->token) == $token;
            if (!$checkHashToken){
                return $this->invalidResponse("Token tidak valid.");
            }
            $datas["password"] = Hash::make($request->password);
            $update = $user->update($datas);

            $passwordForgot = PasswordReset::query()->where('email', '=', $email)
                    ->where("active", "=", 1)->update(["active" => 0]);

            DB::commit();
            return $this->baseResponse('Success update password', $update);

        } catch (Exception $e) {
            DB::rollback();
            return $this->failedActionResponse($e, "Gagal memperbarui kata sandi.");
       }
    }
}
