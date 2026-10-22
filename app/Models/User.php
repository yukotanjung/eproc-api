<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Ramsey\Uuid\Uuid;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PasswordActivate;
use App\Models\PasswordReset;
use App\Mail\MailPasswordReset;
use App\Notifications\UserPasswordActivation;
use App\Notifications\EmailForgotPassword;
use App\Notifications\EmailChange;
use App\Notifications\EmailVerification;
use App\Notifications\EmailDeviceOffline;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Auth;


class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasUuids, ModelTrait, SoftDeletes;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'employee_code',
        'email',
        'password',
        'photo',
        'email_verified_at',
        'last_login'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'photo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $appends = ['photo_url'];

    public $filterable = [
        'name',
        'username',
        'employee_code',
        'email',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function scopegetUser($query, $request, $page, $pageSize, $all)
    {
        $adminEmail = config('app.admin_email');
        $data = $query->filter()->order()->where('email', '<>', $adminEmail);
        if ($all == 'true') {
            $data = $data->orderBy("created_at", 'desc')->get();
        } else {
            $data = $data->orderBy("created_at", 'desc')->paginate($pageSize, ['*'], 'page', $page);
        }
        return $data;
    }

    public function sendPasswordActivation($callbackUrl)
    {

        $to = $this->email;
        $nameUser = $this->name;
        $token = md5(Uuid::uuid1());
        PasswordActivate::query()
            ->where("user_id", "=", $this->id)
            ->update(["active" => 0]);
        $result = PasswordActivate::query()
            ->create(["user_id" => $this->id, "email" => $to, "token" => $token, "active" => 1]);
        if ($result) {
            return $this->notify(new UserPasswordActivation($token, $to, $nameUser, $callbackUrl));
            return true;
        } else {
            return false;
        }
    }

    public function sendDeviceOffline($devices)
    {
        Notification::route('mail', [
            $this->email => $this->name,
        ])->notify(new EmailDeviceOffline($devices, $this->name));
    }

    public function sendPasswordReset($callbackUrl)
    {
        $token = md5(Uuid::uuid1());
        PasswordReset::query()
            ->where("email", "=", $this->email)
            ->update(["active" => 0]);
        $result = PasswordReset::query()
            ->create(["email" => $this->email, "token" => $token]);
        if ($result) {

            $this->notify(new EmailForgotPassword($token, $this->email, $this->name, $callbackUrl));
            return true;
        } else {
            return false;
        }
    }

    public function sendEmailVerification($nameUser = null, $email = null, $callbackUrl = null)
    {


        $to = $email;
        $token = md5(Uuid::uuid1());
        PasswordActivate::query()
        ->where("email", "=", $to)
        ->update(["active" => 0]);
        $result = PasswordActivate::query()
        ->create(["email" => $to, "user_id"=> $this->id, "token" => $token, "active" => 1]);
        if ($result) {

            $this->notify(new EmailChange($nameUser, $email));

            Notification::route('mail', [
                $email => $this->name,
            ])->notify(new EmailVerification($token, $email, $this->name, $this->id, $callbackUrl));
            return true;
        } else {
            return false;
        }
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, "role_user");
    }

    public function getPermissionsAttribute()
    {
        $permissions = new Collection([]);
        foreach ($this->roles as $role) {
            $permissions = $permissions->merge($role->permissions);
        }
        return $permissions;
    }

    public function hasPermission($param)
    {

        $permissions = $this->permissions;
        foreach ($permissions as $permission) {
            $permissionName = $permission->subject.$permission->key.$permission->action;
            if ($permissionName == $param)
                return true;
        }
        return false;
    }

    public function scopeFilter($query)
    {
        return $this->filter_model($query, $this);
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

    public function getPhotoUrlAttribute()
    {
        if ($this->photo)
            return route('make.file', ["token" => encrypt(["name" => $this->name, "path" => $this->photo])]);
        return null;
    }
}
