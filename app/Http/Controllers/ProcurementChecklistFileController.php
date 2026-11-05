<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\ProcurementChecklistFile;
use App\Services\ProcurementScheduleService;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementChecklistFileController extends Controller
{
    use RuleTrait;

    public function index(Request $request, $id)
    {
        if ($input_error = $this->check_input_validation()) {
            return $this->errorResponses($input_error);
        }

        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        app(ProcurementScheduleService::class)->getOrderedSchedules($procurement);

        $query = $procurement->checklistFiles()->with('schedule');

        if ($request->filled('procurement_schedule_id')) {
            $query->where('procurement_schedule_id', (int) $request->input('procurement_schedule_id'));
        }

        $items = $query
            ->orderBy('procurement_schedule_id')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcurementChecklistFile $item) => $item->toApiArray())
            ->values()
            ->all();

        return $this->baseResponse('Success get procurement checklist files', $items);
    }

    public function show($id, $checklistFileId)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        $item = $procurement->checklistFiles()
            ->with('schedule')
            ->find($checklistFileId);

        if (!$item) {
            return $this->invalidResponse('No data');
        }

        return $this->baseResponse('Success get procurement checklist file', $item->toApiArray());
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

            app(ProcurementScheduleService::class)->getOrderedSchedules($procurement);

            $schedule = $procurement->schedules()->find((int) $request->input('procurement_schedule_id'));
            if (!$schedule) {
                return $this->errorValidateResponse('Invalid procurement schedule for this procurement');
            }

            if (!(bool) $schedule->requires_file) {
                return $this->errorValidateResponse('Selected schedule stage does not require file upload');
            }

            $isRequired = $request->has('is_required') && $request->input('is_required') !== null
                ? filter_var($request->input('is_required'), FILTER_VALIDATE_BOOLEAN)
                : true;

            $fileTypes = ProcurementChecklistFile::normalizeFileTypes($request->input('file_types', []));

            DB::beginTransaction();

            $checklistFile = ProcurementChecklistFile::create([
                'procurement_id'          => $procurement->id,
                'procurement_schedule_id' => $schedule->id,
                'name'                    => $request->input('name'),
                'max_size'                => (int) $request->input('max_size'),
                'file_types'              => $fileTypes,
                'is_required'             => $isRequired,
                'description'             => $request->input('description'),
            ]);

            DB::commit();

            return $this->baseResponse(
                'Success create procurement checklist file',
                $checklistFile->load('schedule')->toApiArray()
            );
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function update(Request $request, $id, $checklistFileId)
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

            $checklistFile = $procurement->checklistFiles()->find($checklistFileId);
            if (!$checklistFile) {
                return $this->invalidResponse('No data');
            }

            $targetScheduleId = $request->filled('procurement_schedule_id')
                ? (int) $request->input('procurement_schedule_id')
                : (int) $checklistFile->procurement_schedule_id;

            $schedule = $procurement->schedules()->find($targetScheduleId);
            if (!$schedule) {
                return $this->errorValidateResponse('Invalid procurement schedule for this procurement');
            }

            if (!(bool) $schedule->requires_file) {
                return $this->errorValidateResponse('Selected schedule stage does not require file upload');
            }

            $updates = [
                'procurement_schedule_id' => $schedule->id,
                'name'                    => $request->input('name'),
                'max_size'                => (int) $request->input('max_size'),
                'file_types'              => ProcurementChecklistFile::normalizeFileTypes($request->input('file_types', [])),
                'description'             => $request->has('description')
                    ? $request->input('description')
                    : $checklistFile->description,
            ];

            if ($request->has('is_required') && $request->input('is_required') !== null) {
                $updates['is_required'] = filter_var($request->input('is_required'), FILTER_VALIDATE_BOOLEAN);
            }

            DB::beginTransaction();
            $checklistFile->update($updates);
            DB::commit();

            return $this->baseResponse(
                'Success update procurement checklist file',
                $checklistFile->fresh(['schedule'])->toApiArray()
            );
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id, $checklistFileId)
    {
        try {
            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            if ($procurement->isCancelled()) {
                return $this->errorValidateResponse('Cannot update cancelled procurement');
            }

            $checklistFile = $procurement->checklistFiles()->find($checklistFileId);
            if (!$checklistFile) {
                return $this->invalidResponse('No data');
            }

            DB::beginTransaction();
            $checklistFile->delete();
            DB::commit();

            return $this->baseResponse('Success delete procurement checklist file', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
