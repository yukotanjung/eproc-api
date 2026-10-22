<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\RoleTrait;
use App\Types\RoleAccess;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use RoleTrait;

    public function login(Request $request)
    {
        $loginInput = $request->input('login') ?? $request->input('email') ?? $request->input('username');
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $loginInput, 'password' => $request->password];
        try {
            if (!($token = JWTAuth::attempt($credentials))) {
                return $this->invalidResponse('Invalid credentials', 400);
            }
        } catch (JWTException $e) {
            return $this->invalidResponse('Could not create token', 500);
        }
        $user = Auth::user();
        $roles = $this->transformRoles($user->roles);
        $result = [
            'id'            => $user->id,
            'name'          => $user->name,
            'username'      => $user->username,
            'employee_code' => $user->employee_code,
            'email'         => $user->email,
            'photo_url'     => $user->photo_url,
            'token'         => $token,
            'roles'         => $roles
        ];
        return $this->baseResponse('Login successful', $result);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return $this->baseResponse('User successfully signed out', null);
    }

    public function me()
    {
        $user = auth()->user();
        $result = [
            'id'            => $user->id,
            'name'          => $user->name,
            'username'      => $user->username,
            'employee_code' => $user->employee_code,
            'email'         => $user->email,
            'last_login'    => $user->last_login,
        ];
        return $this->baseResponse('User profile', $result);
    }
}
