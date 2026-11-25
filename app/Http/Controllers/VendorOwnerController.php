<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorOwnerHandlerTrait;
use Illuminate\Http\Request;

class VendorOwnerController extends Controller
{
    use RuleTrait, VendorOwnerHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listOwners($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createOwner($request, $vendor->id);
    }

    public function show($vendorId, $ownerId)
    {
        return $this->getOwnerDetail((int) $vendorId, $ownerId);
    }

    public function update(Request $request, $vendorId, $ownerId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyOwner($request, (int) $vendorId, $ownerId);
    }

    public function destroy($vendorId, $ownerId)
    {
        return $this->removeOwner((int) $vendorId, $ownerId);
    }

    public function downloadFile($vendorId, $ownerId, $type)
    {
        return $this->serveOwnerFile((int) $vendorId, $ownerId, $type);
    }

    public function downloadZip($vendorId, $ownerId)
    {
        return $this->serveOwnerZip((int) $vendorId, $ownerId);
    }
}
