<?php

namespace App\Traits;

use App\Models\VendorEquipment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait VendorEquipmentHandlerTrait
{
    private const EQUIPMENT_STORAGE_DIR = 'vendor/peralatan';

    protected function listEquipments(Request $request, int $vendorId)
    {
        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = VendorEquipment::getVendorEquipment($request, $page, $pageSize, $all, $vendorId);
        $result = $data->map(fn (VendorEquipment $item) => $item->toApiArray());

        return $this->baseResponse('Berhasil mengambil daftar data peralatan', $result, !$all ? $this->pagination($data) : null);
    }

    protected function createEquipment(Request $request, int $vendorId)
    {
        try {
            $disk = Storage::disk('local');
            $photoPath = $request->file('photo')->store(self::EQUIPMENT_STORAGE_DIR, 'local');

            DB::beginTransaction();

            $equipment = VendorEquipment::create([
                'vendor_id'        => $vendorId,
                'equipment_type'   => $request->equipment_type,
                'brand'            => $request->brand,
                'quantity'         => $request->quantity,
                'capacity'         => $request->capacity,
                'manufacture_year' => $request->manufacture_year,
                'condition'        => $request->condition,
                'location'         => $request->location,
                'photo_path'       => $photoPath,
            ]);

            DB::commit();

            return $this->baseResponse('Data peralatan berhasil dibuat', $equipment->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            if (!empty($photoPath) && isset($disk) && $disk->exists($photoPath)) {
                $disk->delete($photoPath);
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function getEquipmentDetail(int $vendorId, $equipmentId)
    {
        $equipment = VendorEquipment::where('vendor_id', $vendorId)->find($equipmentId);
        if (!$equipment) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        return $this->baseResponse('Berhasil mengambil detail data peralatan', $equipment->toApiArray());
    }

    protected function modifyEquipment(Request $request, int $vendorId, $equipmentId)
    {
        try {
            $equipment = VendorEquipment::where('vendor_id', $vendorId)->find($equipmentId);
            if (!$equipment) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $disk = Storage::disk('local');
            $updates = [
                'equipment_type'   => $request->equipment_type,
                'brand'            => $request->brand,
                'quantity'         => $request->quantity,
                'capacity'         => $request->capacity,
                'manufacture_year' => $request->manufacture_year,
                'condition'        => $request->condition,
                'location'         => $request->location,
            ];

            $oldPath = null;
            $newPath = null;

            if ($request->hasFile('photo')) {
                $newPath = $request->file('photo')->store(self::EQUIPMENT_STORAGE_DIR, 'local');
                $oldPath = $equipment->photo_path;
                $updates['photo_path'] = $newPath;
            }

            DB::beginTransaction();
            $equipment->update($updates);
            DB::commit();

            if ($oldPath && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }

            return $this->baseResponse('Data peralatan berhasil diperbarui', $equipment->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            if ($newPath && isset($disk) && $disk->exists($newPath)) {
                $disk->delete($newPath);
            }
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function removeEquipment(int $vendorId, $equipmentId)
    {
        try {
            $equipment = VendorEquipment::where('vendor_id', $vendorId)->find($equipmentId);
            if (!$equipment) {
                return $this->invalidResponse('Data tidak ditemukan');
            }

            $photoPath = $equipment->photo_path;

            DB::beginTransaction();
            $equipment->delete();
            DB::commit();

            $disk = Storage::disk('local');
            if ($photoPath && $disk->exists($photoPath)) {
                $disk->delete($photoPath);
            }

            return $this->baseResponse('Data peralatan berhasil dihapus', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    protected function serveEquipmentFile(int $vendorId, $equipmentId)
    {
        $equipment = VendorEquipment::where('vendor_id', $vendorId)->find($equipmentId);
        if (!$equipment) {
            return $this->invalidResponse('Data tidak ditemukan', 404);
        }

        $path = $equipment->photo_path;
        $disk = Storage::disk('local');

        if (empty($path) || !$disk->exists($path)) {
            return $this->invalidResponse('File tidak ditemukan', 404);
        }

        return response()->file($disk->path($path));
    }
}
