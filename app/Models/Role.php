<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory,HasUuids,ModelTrait,SoftDeletes;

    protected $guarded = [];

    public function scopegetRole($query,$request,$page,$pageSize,$all)
    {
        $data = $query->withCount('permissions','users')->filter()->order();
        if($all == 'true'){
            $data = $data->orderBy("created_at", 'desc')->get();
        }else{
            $data = $data->orderBy("created_at", 'desc')->paginate($pageSize, ['*'], 'page', $page);
        }
        return $data;
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class,'role_permission');
    }

    public function users()
    {
        return $this->belongsToMany(User::class,"role_user");
    }

    public function scopeFilter($query)
    {
        return $this->filter_model($query, $this);
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

}
