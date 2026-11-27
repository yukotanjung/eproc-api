<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorEquipmentHandlerTrait;
use Illuminate\Http\Request;

class VendorEquipmentController extends Controller
{
    use RuleTrait, VendorEquipmentHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listEquipments($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createEquipment($request, $vendor->id);
    }

    public function show($vendorId, $equipmentId)
    {
        return $this->getEquipmentDetail((int) $vendorId, $equipmentId);
    }

    public function update(Request $request, $vendorId, $equipmentId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyEquipment($request, (int) $vendorId, $equipmentId);
    }

    public function destroy($vendorId, $equipmentId)
    {
        return $this->removeEquipment((int) $vendorId, $equipmentId);
    }

    public function downloadFile($vendorId, $equipmentId)
    {
        return $this->serveEquipmentFile((int) $vendorId, $equipmentId);
    }
}
