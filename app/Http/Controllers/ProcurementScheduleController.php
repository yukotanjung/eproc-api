<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\User;
use App\Services\ProcurementScheduleService;
use App\Traits\RuleTrait;
use DomainException;
use Exception;
use Illuminate\Http\Request;

class ProcurementScheduleController extends Controller
{
    use RuleTrait;

    public function __construct(
        private readonly ProcurementScheduleService $scheduleService
    ) {}

    public function index($id)
    {
        $procurement = Procurement::find($id);
        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        return $this->baseResponse(
            'Success get procurement schedule',
            $this->scheduleService->listForProcurement($procurement)
        );
    }

    public function generate(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            $save = $request->has('save') ? filter_var($request->save, FILTER_VALIDATE_BOOLEAN) : false;

            if ($save) {
                $authUser = auth('api')->user();
                $user = $authUser ? User::with('roles.permissions')->find($authUser->id) : null;
                if (!$user || !$user->hasPermission('procurement_update')) {
                    return $this->invalidResponse('Your request is not allowed');
                }
            }

            $result = $this->scheduleService->generate($procurement, [
                'start_date'    => $request->input('start_date'),
                'from_sequence' => $request->input('from_sequence', 1),
                'start_time'    => $request->input('start_time', ProcurementScheduleService::DEFAULT_START_TIME),
                'end_time'      => $request->input('end_time', ProcurementScheduleService::DEFAULT_END_TIME),
                'save'          => $save,
            ]);

            return $this->baseResponse('Success generate procurement schedule', $result);
        } catch (DomainException $e) {
            return $this->errorValidateResponse($e->getMessage());
        } catch (Exception $e) {
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function save(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $procurement = Procurement::find($id);
            if (!$procurement) {
                return $this->invalidResponse('No data');
            }

            $result = $this->scheduleService->save($procurement, $request->input('schedules', []));

            return $this->baseResponse('Success save procurement schedule', $result);
        } catch (DomainException $e) {
            return $this->errorValidateResponse($e->getMessage());
        } catch (Exception $e) {
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function publicShow($id)
    {
        $procurement = Procurement::where('is_active', true)
            ->where('status', '<>', Procurement::STATUS_CANCELLED)
            ->find($id);

        if (!$procurement) {
            return $this->invalidResponse('No data');
        }

        return $this->baseResponse('Success get public procurement schedule', [
            'procurement' => [
                'id'                    => $procurement->id,
                'job_number'            => $procurement->job_number,
                'name'                  => $procurement->name,
                'planned_date'          => $procurement->planned_date ? $procurement->planned_date->format('Y-m-d') : null,
                'budget_year'           => (int) $procurement->budget_year,
                'project_location'      => $procurement->project_location,
                'announcement_location' => $procurement->announcement_location,
                'status'                => $procurement->status,
            ],
            'schedules'   => $this->scheduleService->listPublicForProcurement($procurement),
        ]);
    }
}
