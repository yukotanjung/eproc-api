<?php

namespace App\Models;

use App\Notifications\EmailForgotPassword;
use App\Notifications\VendorEmailVerification;
use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Contracts\JWTSubject;

class VendorUser extends Authenticatable implements JWTSubject
{
    use HasFactory, ModelTrait, Notifiable, SoftDeletes;

    protected $table = 'vendor_users';

    protected $fillable = [
        'vendor_id',
        'name',
        'username',
        'email',
        'phone',
        'photo',
        'password',
        'is_active',
        'email_verified_at',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'photo',
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login'        => 'datetime',
            'password'          => 'hashed',
        ];
    }

    protected $appends = ['photo_url'];

    public $filterable = [
        'name',
        'username',
        'email',
        'phone',
        'vendor_id',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'guard'     => 'vendor',
            'vendor_id' => $this->vendor_id,
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeGetVendorUser($query, $request, $page, $pageSize, $all, $vendorId = null)
    {
        $data = $query->with('vendor')->filter();
        if ($vendorId !== null) {
            $data->where('vendor_id', $vendorId);
        }
        $data->order();

        if ($all == 'true' || $all === true) {
            return $data->orderBy('created_at', 'desc')->get();
        }
        return $data->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $page);
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
        if ($this->photo) {
            return route('make.file', ['token' => encrypt(['name' => $this->name, 'path' => $this->photo])]);
        }
        return null;
    }

    public function sendVerificationEmail(?string $callbackUrl = null): bool
    {
        $token = Str::random(64);
        PasswordActivate::query()
            ->where('email', '=', $this->email)
            ->update(['active' => 0]);

        $result = PasswordActivate::query()->create([
            'user_id' => (string) $this->id,
            'email'   => $this->email,
            'token'   => $token,
            'active'  => 1,
        ]);

        if ($result) {
            $this->notify(new VendorEmailVerification($token, $this->email, $this->name, $callbackUrl));
            return true;
        }
        return false;
    }

    public function sendPasswordReset($callbackUrl)
    {
        $token = md5(Uuid::uuid1());
        PasswordReset::query()
            ->where('email', '=', $this->email)
            ->update(['active' => 0]);
        $result = PasswordReset::query()
            ->create([
                'email' => $this->email,
                'token' => $token,
            ]);
        if ($result) {
            $this->notify(new EmailForgotPassword($token, $this->email, $this->name, $callbackUrl));
            return true;
        }
        return false;
    }

    public function toApiArray(): array
    {
        return [
            'id'                => (int) $this->id,
            'vendor_id'         => (int) $this->vendor_id,
            'name'              => $this->name,
            'username'          => $this->username,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'photo_url'         => $this->photo_url,
            'is_active'         => (bool) $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'last_login'        => $this->last_login,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
