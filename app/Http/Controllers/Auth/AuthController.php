<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\RoleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    use RoleTrait;

    public function login(Request $request)
    {
        $loginInput = trim((string) ($request->input('login') ?? $request->input('email') ?? $request->input('username') ?? ''));
        $validator = Validator::make(
            [
                'login'    => $loginInput,
                'password' => $request->input('password'),
            ],
            [
                'login'    => ['required', 'string'],
                'password' => ['required', 'string'],
            ],
            [
                'login.required'    => 'Username atau email wajib diisi.',
                'password.required' => 'Kata sandi wajib diisi.',
            ]
        );

        if ($validator->fails()) {
            return $this->errorValidateResponse($validator->errors()->first(), $validator->errors(), 422);
        }

        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $loginInput, 'password' => $request->input('password')];

        try {
            if (!($token = JWTAuth::attempt($credentials))) {
                return $this->invalidResponse('Username/email atau kata sandi salah.', 401);
            }
        } catch (JWTException $e) {
            return $this->invalidResponse('Gagal membuat token autentikasi.', 500);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->load('roles.permissions');

        return $this->baseResponse('Login successful', $this->formatAuthUser($user, $token));
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return $this->baseResponse('User successfully signed out', null);
    }

    public function me()
    {
        /** @var User $user */
        $user = auth()->user();
        $user->load('roles.permissions');

        return $this->baseResponse('User profile', $this->formatAuthUser($user));
    }

    private function formatAuthUser(User $user, ?string $token = null): array
    {
        $permissions = $user->roles
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('permission')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $payload = [
            'id'            => $user->id,
            'name'          => $user->name,
            'username'      => $user->username,
            'employee_code' => $user->employee_code,
            'email'         => $user->email,
            'photo_url'     => $user->photo_url,
            'last_login'    => $user->last_login,
            'roles'         => $this->transformRoles($user->roles),
            'permissions'   => $permissions,
        ];

        if ($token !== null) {
            $payload['token'] = $token;
        }

        return $payload;
    }
}
