<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Procurement;
use App\Traits\RuleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? filter_var($request->all, FILTER_VALIDATE_BOOLEAN) : false;

        $data = Procurement::getProcurement($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            return $item->toListApiArray();
        });

        return $this->baseResponse('Success get list procurement', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $result = Procurement::create([
                'job_number'            => $request->job_number,
                'name'                  => $request->name,
                'budget_ceiling'        => $request->budget_ceiling,
                'estimated_price'       => $request->estimated_price,
                'planned_date'          => $request->planned_date,
                'job_type_id'           => $request->job_type_id,
                'qualification_id'      => $request->qualification_id,
                'funding_source_id'     => $request->funding_source_id,
                'budget_year'           => $request->budget_year,
                'project_location'      => $request->project_location,
                'procurement_method_id' => $request->procurement_method_id,
                'announcement_location' => $request->announcement_location,
                'is_active'             => true,
            ]);

            $result->load(['jobType', 'qualification', 'fundingSource', 'procurementMethod']);

            DB::commit();
            return $this->baseResponse('Success create procurement', $result->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = Procurement::with([
            'jobType',
            'qualification',
            'fundingSource',
            'procurementMethod',
        ])->find($id);

        if ($data) {
            return $this->baseResponse('Success', $data->toApiArray());
        }
        return $this->invalidResponse('No data');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = Procurement::find($id);
            if ($data) {
                if ($data->status === Procurement::STATUS_CANCELLED) {
                    return $this->errorValidateResponse('Cannot update cancelled procurement');
                }

                DB::beginTransaction();

                $data->update([
                    'job_number'            => $request->job_number,
                    'name'                  => $request->name,
                    'budget_ceiling'        => $request->budget_ceiling,
                    'estimated_price'       => $request->estimated_price,
                    'planned_date'          => $request->planned_date,
                    'job_type_id'           => $request->job_type_id,
                    'qualification_id'      => $request->qualification_id,
                    'funding_source_id'     => $request->funding_source_id,
                    'budget_year'           => $request->budget_year,
                    'project_location'      => $request->project_location,
                    'procurement_method_id' => $request->procurement_method_id,
                    'announcement_location' => $request->announcement_location,
                    'is_active'             => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
                ]);

                $data->load(['jobType', 'qualification', 'fundingSource', 'procurementMethod']);

                DB::commit();
                return $this->baseResponse('Success update procurement', $data->toApiArray());
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
            $data = Procurement::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->delete();

                DB::commit();
                return $this->baseResponse('Success delete procurement', null);
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function cancel(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $data = Procurement::find($id);
            if ($data) {
                if ($data->status === Procurement::STATUS_CANCELLED) {
                    return $this->errorValidateResponse('Procurement is already cancelled');
                }

                DB::beginTransaction();

                $reason = $request->input('reason') ?? $request->input('cancellation_reason');

                $data->update([
                    'status'              => Procurement::STATUS_CANCELLED,
                    'cancellation_reason' => $reason,
                    'cancelled_at'        => now(),
                ]);

                $data->load(['jobType', 'qualification', 'fundingSource', 'procurementMethod']);

                DB::commit();
                return $this->baseResponse('Success cancel procurement', $data->toApiArray());
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
