<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use App\Traits\VendorOwnerHandlerTrait;
use Illuminate\Http\Request;

class VendorOwnerController extends Controller
{
    use RuleTrait, VendorOwnerHandlerTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->listOwners($request, (int) $authUser->vendor_id);
    }

    public function store(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->createOwner($request, (int) $authUser->vendor_id);
    }

    public function show($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->getOwnerDetail((int) $authUser->vendor_id, $id);
    }

    public function update(Request $request, $id)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->modifyOwner($request, (int) $authUser->vendor_id, $id);
    }

    public function destroy($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->removeOwner((int) $authUser->vendor_id, $id);
    }

    public function downloadFile($id, $type)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveOwnerFile((int) $authUser->vendor_id, $id, $type);
    }

    public function downloadZip($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveOwnerZip((int) $authUser->vendor_id, $id);
    }
}
