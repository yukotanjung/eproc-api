<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorExpertHandlerTrait;
use Illuminate\Http\Request;

class VendorExpertController extends Controller
{
    use RuleTrait, VendorExpertHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listExperts($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createExpert($request, $vendor->id);
    }

    public function show($vendorId, $expertId)
    {
        return $this->getExpertDetail((int) $vendorId, $expertId);
    }

    public function update(Request $request, $vendorId, $expertId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyExpert($request, (int) $vendorId, $expertId);
    }

    public function destroy($vendorId, $expertId)
    {
        return $this->removeExpert((int) $vendorId, $expertId);
    }

    public function downloadFile($vendorId, $expertId, $type)
    {
        return $this->serveExpertFile((int) $vendorId, $expertId, $type);
    }

    public function downloadZip($vendorId, $expertId)
    {
        return $this->serveExpertZip((int) $vendorId, $expertId);
    }
}
