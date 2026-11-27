<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Traits\RuleTrait;
use App\Traits\VendorTaxHandlerTrait;
use Illuminate\Http\Request;

class VendorTaxController extends Controller
{
    use RuleTrait, VendorTaxHandlerTrait;

    public function index(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->listTaxes($request, $vendor->id);
    }

    public function store(Request $request, $vendorId)
    {
        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return $this->invalidResponse('Data tidak ditemukan');
        }
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->createTax($request, $vendor->id);
    }

    public function show($vendorId, $taxId)
    {
        return $this->getTaxDetail((int) $vendorId, $taxId);
    }

    public function update(Request $request, $vendorId, $taxId)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        return $this->modifyTax($request, (int) $vendorId, $taxId);
    }

    public function destroy($vendorId, $taxId)
    {
        return $this->removeTax((int) $vendorId, $taxId);
    }

    public function downloadFile($vendorId, $taxId)
    {
        return $this->serveTaxFile((int) $vendorId, $taxId);
    }

    public function downloadZip($vendorId, $taxId)
    {
        return $this->serveTaxZip((int) $vendorId, $taxId);
    }
}
