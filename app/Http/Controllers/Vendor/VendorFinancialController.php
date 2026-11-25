<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use App\Traits\VendorFinancialHandlerTrait;
use Illuminate\Http\Request;

class VendorFinancialController extends Controller
{
    use RuleTrait, VendorFinancialHandlerTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->listFinancials($request, (int) $authUser->vendor_id);
    }

    public function store(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->createFinancial($request, (int) $authUser->vendor_id);
    }

    public function show($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->getFinancialDetail((int) $authUser->vendor_id, $id);
    }

    public function update(Request $request, $id)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->modifyFinancial($request, (int) $authUser->vendor_id, $id);
    }

    public function destroy($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->removeFinancial((int) $authUser->vendor_id, $id);
    }
}
