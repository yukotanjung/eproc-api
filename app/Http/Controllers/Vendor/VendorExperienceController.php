<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorUser;
use App\Traits\RuleTrait;
use App\Traits\VendorExperienceHandlerTrait;
use Illuminate\Http\Request;

class VendorExperienceController extends Controller
{
    use RuleTrait, VendorExperienceHandlerTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->listExperiences($request, (int) $authUser->vendor_id);
    }

    public function store(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->createExperience($request, (int) $authUser->vendor_id);
    }

    public function show($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->getExperienceDetail((int) $authUser->vendor_id, $id);
    }

    public function update(Request $request, $id)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->modifyExperience($request, (int) $authUser->vendor_id, $id);
    }

    public function destroy($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->removeExperience((int) $authUser->vendor_id, $id);
    }

    public function downloadFile($id, $type)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveExperienceFile((int) $authUser->vendor_id, $id, $type);
    }

    public function downloadZip($id)
    {
        /** @var VendorUser $authUser */
        $authUser = auth('vendor')->user();

        return $this->serveExperienceZip((int) $authUser->vendor_id, $id);
    }
}
