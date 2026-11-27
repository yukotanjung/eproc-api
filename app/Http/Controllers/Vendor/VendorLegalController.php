<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use App\Traits\VendorLegalHandlerTrait;
use Illuminate\Http\Request;

class VendorLegalController extends Controller
{
    use RuleTrait, VendorLegalHandlerTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->listLegals($request, (int) $authUser->vendor_id);
    }

    public function store(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->createLegal($request, (int) $authUser->vendor_id);
    }

    public function show($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->getLegalDetail((int) $authUser->vendor_id, $id);
    }

    public function update(Request $request, $id)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->modifyLegal($request, (int) $authUser->vendor_id, $id);
    }

    public function destroy($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->removeLegal((int) $authUser->vendor_id, $id);
    }

    public function downloadFile($id, $type)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveLegalFile((int) $authUser->vendor_id, $id, $type);
    }

    public function downloadZip($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveLegalZip((int) $authUser->vendor_id, $id);
    }
}
