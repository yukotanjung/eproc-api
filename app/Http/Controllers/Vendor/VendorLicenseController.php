<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorLicense;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use App\Traits\VendorLicenseHandlerTrait;
use Illuminate\Http\Request;

class VendorLicenseController extends Controller
{
    use RuleTrait, VendorLicenseHandlerTrait;

    public function types()
    {
        return $this->baseResponse('Berhasil mengambil daftar jenis perizinan', VendorLicense::TYPES);
    }

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->listLicenses($request, (int) $authUser->vendor_id);
    }

    public function store(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->createLicense($request, (int) $authUser->vendor_id);
    }

    public function show($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->getLicenseDetail((int) $authUser->vendor_id, $id);
    }

    public function update(Request $request, $id)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->modifyLicense($request, (int) $authUser->vendor_id, $id);
    }

    public function destroy($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->removeLicense((int) $authUser->vendor_id, $id);
    }

    public function downloadFile($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveLicenseFile((int) $authUser->vendor_id, $id);
    }

    public function downloadZip($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveLicenseZip((int) $authUser->vendor_id, $id);
    }
}
