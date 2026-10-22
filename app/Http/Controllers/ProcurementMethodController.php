<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ProcurementMethod;
use App\Traits\RuleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementMethodController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        // accepts ?all=true, ?all=1, ?all=yes; the shared 'boolean' rule rejects the word "true"
        $all = $request->has('all') ? filter_var($request->all, FILTER_VALIDATE_BOOLEAN) : false;

        $data = ProcurementMethod::getProcurementMethod($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            return $item->toApiArray();
        });

        return $this->baseResponse('Success get list procurement method', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $result = ProcurementMethod::create([
                'name'        => $request->name,
                'description' => $request->description,
                'is_active'   => true,
            ]);

            DB::commit();
            return $this->baseResponse('Success create procurement method', $result->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = ProcurementMethod::with(['stages' => function ($query) {
            $query->orderBy('sequence');
        }])->find($id);

        if ($data) {
            return $this->baseResponse('Success', $data->toApiArray(true));
        }
        return $this->invalidResponse('No data');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = ProcurementMethod::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->update([
                    'name'        => $request->name,
                    'description' => $request->description,
                    'is_active'   => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
                ]);

                DB::commit();
                return $this->baseResponse('Success update procurement method', $data->toApiArray());
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
            $data = ProcurementMethod::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->stages()->delete();
                $data->delete();

                DB::commit();
                return $this->baseResponse('Success delete procurement method', null);
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
