<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorLegalHandlerTrait;
use Illuminate\Http\Request;

class VendorLegalController extends Controller
{
    use RuleTrait, VendorLegalHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listLegals($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createLegal($request, $vendor->id);
    }

    public function show($vendorId, $legalId)
    {
        return $this->getLegalDetail((int) $vendorId, $legalId);
    }

    public function update(Request $request, $vendorId, $legalId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyLegal($request, (int) $vendorId, $legalId);
    }

    public function destroy($vendorId, $legalId)
    {
        return $this->removeLegal((int) $vendorId, $legalId);
    }

    public function downloadFile($vendorId, $legalId, $type)
    {
        return $this->serveLegalFile((int) $vendorId, $legalId, $type);
    }

    public function downloadZip($vendorId, $legalId)
    {
        return $this->serveLegalZip((int) $vendorId, $legalId);
    }
}
