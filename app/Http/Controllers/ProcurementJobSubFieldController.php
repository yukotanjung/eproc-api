<?php

namespace App\Http\Controllers;

use App\Models\JobSubField;
use App\Models\Procurement;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementJobSubFieldController extends Controller
{
    use RuleTrait;

    public function index($id)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        return $this->baseResponse(
            'Success get procurement job sub fields',
            $this->formatJobSubFields($procurement)
        );
    }

    public function store(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) {
                return $this->errorResponses($input_error);
            }

            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            if ($procurement->isCancelled()) {
                return $this->errorValidateResponse('Cannot update cancelled procurement');
            }

            $ids = Procurement::normalizeIds($request->input('job_sub_field_ids', []));

            if (!empty($ids)) {
                $matchingTypeCount = JobSubField::whereIn('id', $ids)
                    ->where('job_type_id', (int) $procurement->job_type_id)
                    ->count();

                if ($matchingTypeCount !== count($ids)) {
                    return $this->errorValidateResponse('Selected job sub fields must match procurement job type');
                }
            }

            DB::beginTransaction();
            $procurement->jobSubFields()->sync($ids);
            DB::commit();

            return $this->baseResponse(
                'Success save procurement job sub fields',
                $this->formatJobSubFields($procurement)
            );
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id, $jobSubFieldId)
    {
        try {
            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            if ($procurement->isCancelled()) {
                return $this->errorValidateResponse('Cannot update cancelled procurement');
            }

            $exists = $procurement->jobSubFields()
                ->where('job_sub_fields.id', (int) $jobSubFieldId)
                ->exists();

            if (!$exists) {
                return $this->invalidResponse('No data');
            }

            DB::beginTransaction();
            $procurement->jobSubFields()->detach((int) $jobSubFieldId);
            DB::commit();

            return $this->baseResponse(
                'Success delete procurement job sub field',
                $this->formatJobSubFields($procurement)
            );
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    private function formatJobSubFields(Procurement $procurement): array
    {
        return $procurement->jobSubFields()
            ->with(['jobType', 'jobField'])
            ->orderBy('job_sub_fields.id')
            ->get()
            ->map(fn(JobSubField $subField) => $subField->toApiArray())
            ->values()
            ->all();
    }
}
