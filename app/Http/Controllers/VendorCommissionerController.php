<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorCommissionerHandlerTrait;
use Illuminate\Http\Request;

class VendorCommissionerController extends Controller
{
    use RuleTrait, VendorCommissionerHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listCommissioners($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createCommissioner($request, $vendor->id);
    }

    public function show($vendorId, $commissionerId)
    {
        return $this->getCommissionerDetail((int) $vendorId, $commissionerId);
    }

    public function update(Request $request, $vendorId, $commissionerId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyCommissioner($request, (int) $vendorId, $commissionerId);
    }

    public function destroy($vendorId, $commissionerId)
    {
        return $this->removeCommissioner((int) $vendorId, $commissionerId);
    }

    public function downloadFile($vendorId, $commissionerId, $type)
    {
        return $this->serveCommissionerFile((int) $vendorId, $commissionerId, $type);
    }

    public function downloadZip($vendorId, $commissionerId)
    {
        return $this->serveCommissionerZip((int) $vendorId, $commissionerId);
    }
}
