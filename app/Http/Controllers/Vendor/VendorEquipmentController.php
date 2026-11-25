<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorEquipment;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use App\Traits\VendorEquipmentHandlerTrait;
use Illuminate\Http\Request;

class VendorEquipmentController extends Controller
{
    use RuleTrait, VendorEquipmentHandlerTrait;

    public function conditions()
    {
        return $this->baseResponse('Berhasil mengambil daftar kondisi peralatan', VendorEquipment::CONDITIONS);
    }

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->listEquipments($request, (int) $authUser->vendor_id);
    }

    public function store(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->createEquipment($request, (int) $authUser->vendor_id);
    }

    public function show($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->getEquipmentDetail((int) $authUser->vendor_id, $id);
    }

    public function update(Request $request, $id)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->modifyEquipment($request, (int) $authUser->vendor_id, $id);
    }

    public function destroy($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->removeEquipment((int) $authUser->vendor_id, $id);
    }

    public function downloadFile($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveEquipmentFile((int) $authUser->vendor_id, $id);
    }
}
