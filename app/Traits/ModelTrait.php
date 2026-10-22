<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Contracts\Providers\JWT;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Payload;

trait ModelTrait
{
    public function filter_model(Builder $builder, Model $model)
    {
        $data = request()->filter;
        $filterable = $model->filterable;
        $filter_columns = [];
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, $filterable)) {
                    array_push($filter_columns, $key);
                }
            }
            if (count($filter_columns) > 0) {
                for ($i = 0; $i < count($filter_columns); $i++) {
                    if ($data[$filter_columns[$i]] == "") continue;

                    $mode = "where";

                    if ($this->ends_with($filter_columns[$i], "_id") || $filter_columns[$i] == "id") {
                        $filter_string = $data[$filter_columns[$i]];
                    } else {
                        $filter_string = '%' . $data[$filter_columns[$i]] . '%';
                    }
                    // var_dump($filter_columns);
                    $builder->$mode($filter_columns[$i], 'like', $filter_string);
                }
            }

        }

        $search = request()->input('search', "");
            if ($search != "") {
                $builder->where(function ($q) use ($filterable, $search) {
                    $filter_columns = $filterable;
                    if (count($filter_columns) > 0) {
                        for ($i = 0; $i < count($filter_columns); $i++) {
                            $mode = "where";
                            if ($i > 0) $mode = "orWhere";
                            $q->$mode($filter_columns[$i], 'like', '%' . $search . '%');
                        }
                    }
                });
            }
    }

    public function order_model(Builder $builder, Model $model)
    {
        $order_by = request()->input('order_by', "created_at");
        $order_direction = request()->input('order_direction', "desc");
        $builder->when(Schema::hasColumn($model->getTable(), $order_by), function ($q) use ($order_by, $order_direction) {
            $q->orderBy($order_by, $order_direction);
        });
    }

    private function ends_with($haystack, $needle)
    {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }


    function generateJwt($payload)
    {
        $payload = JWTFactory::make($payload);
        $token = JWTAuth::encode($payload)->get();
        return $token;
    }

    function dumpSql($query)
    {
        $queryDump = str_replace(array('?'), array('\'%s\''), $query->toSql());
        $queryDump = vsprintf($queryDump, $query->getBindings());
        return $queryDump;
    }
}
