<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use App\Traits\RuleTrait;

class PermissionController extends Controller
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

        $data = Permission::getPermission($request,$page,$pageSize,$all);


        $result = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'subject' => $item->subject,
                'action' => $item->action,
                'permission' => $item->permission
            ];
        });
        return $this->baseResponse('Success get list permissions', $result, !$all ? $this->pagination($data) : null);
    }

    public function group()
    {
        $permissions = Permission::where('name','<>','Manage Permission')->get();

        $result = $permissions->reduce(function ($prev, $curr) {
            $subject = $curr->subject;
            $action = $curr->action;
            $name = $curr->name;
            $id = $curr->id;

            $existingSubjectIndex = collect($prev)->search(function ($item) use ($subject) {
                return $item['subject'] === $subject;
            });

            if ($existingSubjectIndex !== false) {
                $prev[$existingSubjectIndex]['actions'][] = ['id' => $id, 'action' => $action];
            } else {
                $prev[] = ['subject' => $subject, 'name' => $name, 'actions' => [['id' => $id, 'action' => $action]]];
            }

            return $prev;
        }, []);

        return $this->baseResponse('Success', $result);
    }

    public function show($id)
    {
        $data = Permission::find($id);
        return $this->baseResponse('Success', $data);

    }


}
