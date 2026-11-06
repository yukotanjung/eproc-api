<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) {
            return $this->errorResponses($input_error);
        }

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? filter_var($request->all, FILTER_VALIDATE_BOOLEAN) : false;

        $data = Unit::getUnit($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            return [
                'id'         => (int) $item->id,
                'name'       => $item->name,
                'code'       => $item->code,
                'is_active'  => (bool) $item->is_active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return $this->baseResponse('Success get list unit', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) {
                return $this->errorResponses($input_error);
            }

            DB::beginTransaction();

            $result = Unit::create([
                'name'      => $request->name,
                'code'      => $request->code,
                'is_active' => true,
            ]);

            DB::commit();

            return $this->baseResponse('Success create unit', [
                'id'         => (int) $result->id,
                'name'       => $result->name,
                'code'       => $result->code,
                'is_active'  => (bool) $result->is_active,
                'created_at' => $result->created_at,
                'updated_at' => $result->updated_at,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = Unit::find($id);
        if ($data) {
            return $this->baseResponse('Success', [
                'id'         => (int) $data->id,
                'name'       => $data->name,
                'code'       => $data->code,
                'is_active'  => (bool) $data->is_active,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ]);
        }

        return $this->invalidResponse('No data');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) {
                return $this->errorResponses($input_error);
            }

            $data = Unit::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->update([
                    'name'      => $request->name,
                    'code'      => $request->has('code') ? $request->code : $data->code,
                    'is_active' => $request->has('is_active')
                        ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                        : $data->is_active,
                ]);

                DB::commit();

                return $this->baseResponse('Success update unit', [
                    'id'         => (int) $data->id,
                    'name'       => $data->name,
                    'code'       => $data->code,
                    'is_active'  => (bool) $data->is_active,
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                ]);
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
            $data = Unit::find($id);
            if ($data) {
                DB::beginTransaction();
                $data->delete();
                DB::commit();

                return $this->baseResponse('Success delete unit', null);
            }

            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
