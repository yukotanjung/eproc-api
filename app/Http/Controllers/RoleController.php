<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Role;
use App\Models\User;
use App\Traits\RuleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class RoleController extends Controller
{
    use RuleTrait;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = Role::getRole($request,$page,$pageSize,$all);

        $result = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'total_permissions' => $item->permissions_count,
                'total_users' => $item->users_count,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ];
        });
        return $this->baseResponse('Success get list role', $result, !$all ? $this->pagination($data) : null);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            if($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $datas['name'] = $request->name;
            $datas['description'] = $request->description;
            $result = Role::create($datas);
            if($result){
                $result->permissions()->sync($request->permission_ids);
                $result->permissions;

                DB::commit();
                return $this->baseResponse('Success create role', $result);
            }else{
                return $this->invalidResponse("No data");
            }
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = Role::withCount('permissions','users')->with('permissions')->find($id);
        if($data){
            $result = [
                'id' => $data->id,
                'name' => $data->name,
                'description' => $data->description,
                'total_permissions' => $data->permissions_count,
                'total_users' => $data->users_count,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
                'permissions' => $data->permissions
            ];;
            return $this->baseResponse('Success', $result);
        }else{
            return $this->invalidResponse("No data");
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {

            if($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = Role::find($id);
            if ($data) {
                DB::beginTransaction();
                $update['name'] = $request->name;
                $update['description'] = $request->description;
                $update = $data->update($update);
                $data->permissions()->sync($request->permission_ids);
                $data->permissions;

                DB::commit();
                return $this->baseResponse("Success update role",$data);
            }
            return $this->invalidResponse("No data");

        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $usersCount = User::whereHas('roles', function ($query) use ($id) {
                $query->where('role_id', $id);
            })->count();

            if ($usersCount > 0) {
                return $this->invalidResponse("Cannot delete the roles. There are users still associated with them.");
            }

            $data = Role::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->delete();

                DB::commit();
                return $this->baseResponse("Success delete role",null);
            }
            return $this->invalidResponse("No data");

        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
