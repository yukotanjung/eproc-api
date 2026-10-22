<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends Model
{
    use HasFactory,ModelTrait,HasUuids;

    protected $guarded = [];

    public $filterable = [
        'name',
        'key',
        'subject',
        'action'
    ];

    protected $appends = ['permission'];

    public $timestamps = false;

    public function scopegetPermission($qry,$request,$page,$pageSize,$all){
        $data = $this->filter()->order();
        if($all == 'true'){
            $data = $data->orderBy("subject", 'desc')->get();
        }else{
            $data = $data->orderBy("subject", 'desc')->paginate($pageSize, ['*'], 'page', $page);
        }
        return $data;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class,'role_permission');
    }

    public function scopeFilter($query)
    {
        return $this->filter_model($query, $this);
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

    public function getPermissionAttribute()
    {
        return $this->subject.$this->key.$this->action;
    }

}
