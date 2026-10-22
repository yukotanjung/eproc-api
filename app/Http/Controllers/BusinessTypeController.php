<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\BusinessType;
use App\Traits\RuleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessTypeController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = BusinessType::getBusinessType($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            return [
                'id'          => $item->id,
                'name'        => $item->name,
                'description' => $item->description,
                'is_active'   => (bool) $item->is_active,
                'created_at'  => $item->created_at,
                'updated_at'  => $item->updated_at,
            ];
        });

        return $this->baseResponse('Success get list business type', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $result = BusinessType::create([
                'name'        => $request->name,
                'description' => $request->description,
                'is_active'   => true,
            ]);

            DB::commit();
            return $this->baseResponse('Success create business type', $result);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = BusinessType::find($id);
        if ($data) {
            return $this->baseResponse('Success', [
                'id'          => $data->id,
                'name'        => $data->name,
                'description' => $data->description,
                'is_active'   => (bool) $data->is_active,
                'created_at'  => $data->created_at,
                'updated_at'  => $data->updated_at,
            ]);
        }
        return $this->invalidResponse('No data');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = BusinessType::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->update([
                    'name'        => $request->name,
                    'description' => $request->description,
                    'is_active'   => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
                ]);

                DB::commit();
                return $this->baseResponse('Success update business type', $data);
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $data = BusinessType::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->delete();

                DB::commit();
                return $this->baseResponse('Success delete business type', null);
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
