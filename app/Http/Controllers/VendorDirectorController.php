<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorDirectorHandlerTrait;
use Illuminate\Http\Request;

class VendorDirectorController extends Controller
{
    use RuleTrait, VendorDirectorHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listDirectors($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createDirector($request, $vendor->id);
    }

    public function show($vendorId, $directorId)
    {
        return $this->getDirectorDetail((int) $vendorId, $directorId);
    }

    public function update(Request $request, $vendorId, $directorId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyDirector($request, (int) $vendorId, $directorId);
    }

    public function destroy($vendorId, $directorId)
    {
        return $this->removeDirector((int) $vendorId, $directorId);
    }

    public function downloadFile($vendorId, $directorId, $type)
    {
        return $this->serveDirectorFile((int) $vendorId, $directorId, $type);
    }

    public function downloadZip($vendorId, $directorId)
    {
        return $this->serveDirectorZip((int) $vendorId, $directorId);
    }
}
