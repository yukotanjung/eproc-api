<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Classification;
use App\Traits\RuleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassificationController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = Classification::getClassification($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            return [
                'id'           => $item->id,
                'job_type_id'  => $item->job_type_id,
                'job_field_id' => $item->job_field_id,
                'name'         => $item->name,
                'is_active'    => (bool) $item->is_active,
                'created_at'   => $item->created_at,
                'updated_at'   => $item->updated_at,
                'job_type'     => $item->jobType ? [
                    'id'   => $item->jobType->id,
                    'name' => $item->jobType->name,
                ] : null,
                'job_field'    => $item->jobField ? [
                    'id'   => $item->jobField->id,
                    'name' => $item->jobField->name,
                ] : null,
            ];
        });

        return $this->baseResponse('Success get list classification', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $result = Classification::create([
                'job_type_id'  => $request->job_type_id,
                'job_field_id' => $request->job_field_id,
                'name'         => $request->name,
                'is_active'    => true,
            ]);

            DB::commit();
            return $this->baseResponse('Success create classification', $result->load(['jobType', 'jobField']));
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = Classification::with(['jobType', 'jobField'])->find($id);
        if ($data) {
            return $this->baseResponse('Success', [
                'id'           => $data->id,
                'job_type_id'  => $data->job_type_id,
                'job_field_id' => $data->job_field_id,
                'name'         => $data->name,
                'is_active'    => (bool) $data->is_active,
                'created_at'   => $data->created_at,
                'updated_at'   => $data->updated_at,
                'job_type'     => $data->jobType ? [
                    'id'   => $data->jobType->id,
                    'name' => $data->jobType->name,
                ] : null,
                'job_field'    => $data->jobField ? [
                    'id'   => $data->jobField->id,
                    'name' => $data->jobField->name,
                ] : null,
            ]);
        }
        return $this->invalidResponse('No data');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = Classification::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->update([
                    'job_type_id'  => $request->has('job_type_id') ? $request->job_type_id : $data->job_type_id,
                    'job_field_id' => $request->has('job_field_id') ? $request->job_field_id : $data->job_field_id,
                    'name'         => $request->name,
                    'is_active'    => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
                ]);

                DB::commit();
                return $this->baseResponse('Success update classification', $data->load(['jobType', 'jobField']));
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
            $data = Classification::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->delete();

                DB::commit();
                return $this->baseResponse('Success delete classification', null);
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
