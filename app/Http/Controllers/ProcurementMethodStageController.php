<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ProcurementMethod;
use App\Models\ProcurementMethodStage;
use App\Services\StageSchedule;
use App\Traits\RuleTrait;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementMethodStageController extends Controller
{
    use RuleTrait;

    public function index(Request $request, $id)
    {
        $method = ProcurementMethod::find($id);
        if (!$method) return $this->invalidResponse('No data');

        $result = $this->listPayload((int) $method->id);

        return $this->baseResponse('Success get list procurement method stage', $result);
    }

    public function store(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $method = ProcurementMethod::find($id);
            if (!$method) return $this->invalidResponse('No data');

            DB::beginTransaction();

            $result = ProcurementMethodStage::create([
                'procurement_method_id' => $method->id,
                'sequence'              => ProcurementMethodStage::nextSequence((int) $method->id),
                'name'                  => $request->name,
                'duration_days'         => (int) $request->duration_days,
                'day_type'              => $request->day_type,
                'requires_file'         => filter_var($request->requires_file ?? false, FILTER_VALIDATE_BOOLEAN),
                'page_key'              => $request->page_key,
                'process_key'           => $request->process_key,
                'description'           => $request->description,
                'is_active'             => true,
            ]);

            DB::commit();
            return $this->baseResponse('Success create procurement method stage', $result->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function update(Request $request, $id, $stageId)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $data = ProcurementMethodStage::where('procurement_method_id', $id)->find($stageId);
            if (!$data) return $this->invalidResponse('No data');

            DB::beginTransaction();

            // sequence stays out of update: order changes go through reorder().
            $data->update([
                'name'          => $request->name,
                'duration_days' => (int) $request->duration_days,
                'day_type'      => $request->day_type,
                'requires_file' => filter_var($request->requires_file ?? false, FILTER_VALIDATE_BOOLEAN),
                'page_key'      => $request->page_key,
                'process_key'   => $request->process_key,
                'description'   => $request->description,
                'is_active'     => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
            ]);

            DB::commit();
            return $this->baseResponse('Success update procurement method stage', $data->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id, $stageId)
    {
        try {
            $data = ProcurementMethodStage::where('procurement_method_id', $id)->find($stageId);
            if (!$data) return $this->invalidResponse('No data');

            DB::beginTransaction();

            $remaining = ProcurementMethodStage::where('procurement_method_id', $id)
                ->where('id', '<>', $data->id)
                ->orderBy('sequence')
                ->pluck('id')
                ->all();

            // Park the slot on a negative unique value so compaction cannot hit pms_method_seq_uq.
            $data->update(['sequence' => -1 * $data->id]);
            $data->delete();

            ProcurementMethodStage::reorderWithin((int) $id, $remaining);

            DB::commit();
            return $this->baseResponse('Success delete procurement method stage', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function reorder(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $method = ProcurementMethod::find($id);
            if (!$method) return $this->invalidResponse('No data');

            $incoming = array_map('intval', $request->stage_ids);

            $current = ProcurementMethodStage::orderedIds((int) $method->id);
            $sortedCurrent = $current;
            $sortedIncoming = $incoming;
            sort($sortedCurrent);
            sort($sortedIncoming);

            if ($sortedCurrent !== $sortedIncoming) {
                return $this->errorValidateResponse('stage_ids must contain exactly all stage ids of this procurement method');
            }

            DB::beginTransaction();
            ProcurementMethodStage::reorderWithin((int) $method->id, $incoming);
            DB::commit();

            return $this->baseResponse('Success reorder procurement method stage', $this->listPayload((int) $method->id));
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function previewSchedule(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $method = ProcurementMethod::find($id);
            if (!$method) return $this->invalidResponse('No data');

            $stages = ProcurementMethodStage::where('procurement_method_id', $method->id)
                ->where('is_active', true)
                ->orderBy('sequence')
                ->get();

            return $this->baseResponse('Success preview procurement method schedule', [
                'start_date' => $request->start_date,
                'stages'     => StageSchedule::build($stages, CarbonImmutable::parse($request->start_date)),
            ]);
        } catch (Exception $e) {
            return $this->invalidResponse($e->getMessage());
        }
    }

    private function listPayload(int $procurementMethodId): array
    {
        return ProcurementMethodStage::where('procurement_method_id', $procurementMethodId)
            ->orderBy('sequence')
            ->get()
            ->map(fn (ProcurementMethodStage $stage) => $stage->toApiArray())
            ->all();
    }
}
