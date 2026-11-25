<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorExperienceHandlerTrait;
use Illuminate\Http\Request;

class VendorExperienceController extends Controller
{
    use RuleTrait, VendorExperienceHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listExperiences($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createExperience($request, $vendor->id);
    }

    public function show($vendorId, $experienceId)
    {
        return $this->getExperienceDetail((int) $vendorId, $experienceId);
    }

    public function update(Request $request, $vendorId, $experienceId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyExperience($request, (int) $vendorId, $experienceId);
    }

    public function destroy($vendorId, $experienceId)
    {
        return $this->removeExperience((int) $vendorId, $experienceId);
    }

    public function downloadFile($vendorId, $experienceId, $type)
    {
        return $this->serveExperienceFile((int) $vendorId, $experienceId, $type);
    }

    public function downloadZip($vendorId, $experienceId)
    {
        return $this->serveExperienceZip((int) $vendorId, $experienceId);
    }
}
