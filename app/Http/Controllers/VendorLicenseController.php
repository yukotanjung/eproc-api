<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorLicenseHandlerTrait;
use Illuminate\Http\Request;

class VendorLicenseController extends Controller
{
    use RuleTrait, VendorLicenseHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listLicenses($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createLicense($request, $vendor->id);
    }

    public function show($vendorId, $licenseId)
    {
        return $this->getLicenseDetail((int) $vendorId, $licenseId);
    }

    public function update(Request $request, $vendorId, $licenseId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyLicense($request, (int) $vendorId, $licenseId);
    }

    public function destroy($vendorId, $licenseId)
    {
        return $this->removeLicense((int) $vendorId, $licenseId);
    }

    public function downloadFile($vendorId, $licenseId)
    {
        return $this->serveLicenseFile((int) $vendorId, $licenseId);
    }

    public function downloadZip($vendorId, $licenseId)
    {
        return $this->serveLicenseZip((int) $vendorId, $licenseId);
    }
}
