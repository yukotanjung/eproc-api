<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HolidayController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? filter_var($request->all, FILTER_VALIDATE_BOOLEAN) : false;

        $data = Holiday::getHoliday($request, $page, $pageSize, $all);

        $result = $data->map(fn (Holiday $item) => $item->toApiArray());

        return $this->baseResponse('Success get list holiday', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            if (Holiday::whereDate('holiday_date', $request->holiday_date)->exists()) {
                return $this->errorValidateResponse('Holiday date already exists');
            }

            DB::beginTransaction();

            $result = Holiday::create([
                'holiday_date' => $request->holiday_date,
                'name'         => $request->name,
                'description'  => $request->description,
                'is_active'    => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
            ]);

            DB::commit();
            return $this->baseResponse('Success create holiday', $result->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = Holiday::find($id);
        if ($data) {
            return $this->baseResponse('Success', $data->toApiArray());
        }

        return $this->invalidResponse('No data');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = Holiday::find($id);
            if (!$data) {
                return $this->invalidResponse('No data');
            }

            $duplicate = Holiday::whereDate('holiday_date', $request->holiday_date)
                ->where('id', '<>', $data->id)
                ->exists();

            if ($duplicate) {
                return $this->errorValidateResponse('Holiday date already exists');
            }

            DB::beginTransaction();

            $data->update([
                'holiday_date' => $request->holiday_date,
                'name'         => $request->name,
                'description'  => $request->description,
                'is_active'    => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
            ]);

            DB::commit();
            return $this->baseResponse('Success update holiday', $data->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $data = Holiday::find($id);
            if (!$data) {
                return $this->invalidResponse('No data');
            }

            DB::beginTransaction();
            $data->delete();
            DB::commit();

            return $this->baseResponse('Success delete holiday', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
