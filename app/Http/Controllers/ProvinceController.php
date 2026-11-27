<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Traits\RuleTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProvinceController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = Province::getProvince($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            return [
                'id'         => (int) $item->id,
                'name'       => $item->name,
                'is_active'  => (bool) $item->is_active,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return $this->baseResponse('Berhasil mengambil daftar provinsi', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $result = Province::create([
                'name'      => $request->name,
                'is_active' => true,
            ]);

            DB::commit();
            return $this->baseResponse('Provinsi berhasil dibuat', $result);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = Province::find($id);
        if ($data) {
            return $this->baseResponse('Berhasil mengambil detail provinsi', [
                'id'         => (int) $data->id,
                'name'       => $data->name,
                'is_active'  => (bool) $data->is_active,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ]);
        }
        return $this->invalidResponse('Data tidak ditemukan');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = Province::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->update([
                    'name'      => $request->name,
                    'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
                ]);

                DB::commit();
                return $this->baseResponse('Provinsi berhasil diperbarui', $data);
            }
            return $this->invalidResponse('Data tidak ditemukan');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $data = Province::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->delete();

                DB::commit();
                return $this->baseResponse('Provinsi berhasil dihapus', null);
            }
            return $this->invalidResponse('Data tidak ditemukan');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
