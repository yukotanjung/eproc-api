<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\Vendor\VendorAuthController;
use App\Http\Controllers\Vendor\VendorCommissionerController;
use App\Http\Controllers\Vendor\VendorDirectorController;
use App\Http\Controllers\Vendor\VendorEquipmentController;
use App\Http\Controllers\Vendor\VendorExpertController;
use App\Http\Controllers\Vendor\VendorFinancialController;
use App\Http\Controllers\Vendor\VendorLegalController;
use App\Http\Controllers\Vendor\VendorLicenseController;
use App\Http\Controllers\Vendor\VendorOwnerController;
use App\Http\Controllers\Vendor\VendorProfileController;
use App\Http\Controllers\Vendor\VendorTaxController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendor')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [VendorAuthController::class, 'register']);
        Route::post('/login', [VendorAuthController::class, 'login']);
        Route::get('/me', [VendorAuthController::class, 'me'])->middleware('auth:vendor');
        Route::get('/logout', [VendorAuthController::class, 'logout'])->middleware('auth:vendor');

        Route::get('/verify', [VendorAuthController::class, 'verify'])->name('vendor.verify');
        Route::post('/verify', [VendorAuthController::class, 'verify']);
        Route::post('/resend-verification', [VendorAuthController::class, 'resendVerification']);

        Route::prefix('forgot_password')->group(function () {
            Route::post('/send', [VendorAuthController::class, 'sendForgotPasswordLink']);
            Route::post('/check_token', [VendorAuthController::class, 'checkForgotPasswordToken']);
            Route::post('/update_password', [VendorAuthController::class, 'updateForgotPassword']);
        });
    });

    Route::middleware(['throttle:600,1'])->group(function () {
        Route::get('/province', [ProvinceController::class, 'index']);
        Route::get('/province/{id}', [ProvinceController::class, 'show']);
        Route::get('/city', [CityController::class, 'index']);
        Route::get('/city/{id}', [CityController::class, 'show']);
        Route::get('/education-level', [EducationLevelController::class, 'index']);
        Route::get('/education-level/{id}', [EducationLevelController::class, 'show']);
        Route::get('/tax-type', [TaxController::class, 'index']);
        Route::get('/tax-type/{id}', [TaxController::class, 'show']);
    });

    Route::middleware(['auth:vendor', 'throttle:600,1'])->group(function () {
        Route::prefix('profile')->group(function () {
            Route::get('/', [VendorProfileController::class, 'profile']);
            Route::post('/update-profile', [VendorProfileController::class, 'updateProfile']);
            Route::post('/update-password', [VendorProfileController::class, 'updatePassword']);
        });

        Route::prefix('company')->group(function () {
            Route::get('/', [VendorProfileController::class, 'company']);
            Route::post('/', [VendorProfileController::class, 'updateCompany']);
        });

        Route::prefix('user')->group(function () {
            Route::get('/{id}/resend-activation', [VendorProfileController::class, 'resendActivation']);
            Route::get('/', [VendorProfileController::class, 'indexUsers']);
            Route::post('/', [VendorProfileController::class, 'storeUser']);
            Route::get('/{id}', [VendorProfileController::class, 'showUser']);
            Route::post('/{id}', [VendorProfileController::class, 'updateUser']);
            Route::delete('/{id}', [VendorProfileController::class, 'destroyUser']);
        });

        Route::prefix('owner')->group(function () {
            Route::get('/', [VendorOwnerController::class, 'index']);
            Route::post('/', [VendorOwnerController::class, 'store']);
            Route::get('/{id}', [VendorOwnerController::class, 'show']);
            Route::post('/{id}', [VendorOwnerController::class, 'update']);
            Route::delete('/{id}', [VendorOwnerController::class, 'destroy']);
            Route::get('/{id}/file/{type}', [VendorOwnerController::class, 'downloadFile']);
            Route::get('/{id}/download-zip', [VendorOwnerController::class, 'downloadZip']);
        });

        Route::prefix('commissioner')->group(function () {
            Route::get('/', [VendorCommissionerController::class, 'index']);
            Route::post('/', [VendorCommissionerController::class, 'store']);
            Route::get('/{id}', [VendorCommissionerController::class, 'show']);
            Route::post('/{id}', [VendorCommissionerController::class, 'update']);
            Route::delete('/{id}', [VendorCommissionerController::class, 'destroy']);
            Route::get('/{id}/file/{type}', [VendorCommissionerController::class, 'downloadFile']);
            Route::get('/{id}/download-zip', [VendorCommissionerController::class, 'downloadZip']);
        });

        Route::prefix('director')->group(function () {
            Route::get('/', [VendorDirectorController::class, 'index']);
            Route::post('/', [VendorDirectorController::class, 'store']);
            Route::get('/{id}', [VendorDirectorController::class, 'show']);
            Route::post('/{id}', [VendorDirectorController::class, 'update']);
            Route::delete('/{id}', [VendorDirectorController::class, 'destroy']);
            Route::get('/{id}/file/{type}', [VendorDirectorController::class, 'downloadFile']);
            Route::get('/{id}/download-zip', [VendorDirectorController::class, 'downloadZip']);
        });

        Route::prefix('expert')->group(function () {
            Route::get('/', [VendorExpertController::class, 'index']);
            Route::post('/', [VendorExpertController::class, 'store']);
            Route::get('/{id}', [VendorExpertController::class, 'show']);
            Route::post('/{id}', [VendorExpertController::class, 'update']);
            Route::delete('/{id}', [VendorExpertController::class, 'destroy']);
            Route::get('/{id}/file/{type}', [VendorExpertController::class, 'downloadFile']);
            Route::get('/{id}/download-zip', [VendorExpertController::class, 'downloadZip']);
        });

        Route::prefix('financial')->group(function () {
            Route::get('/', [VendorFinancialController::class, 'index']);
            Route::post('/', [VendorFinancialController::class, 'store']);
            Route::get('/{id}', [VendorFinancialController::class, 'show']);
            Route::post('/{id}', [VendorFinancialController::class, 'update']);
            Route::delete('/{id}', [VendorFinancialController::class, 'destroy']);
        });

        Route::prefix('tax')->group(function () {
            Route::get('/', [VendorTaxController::class, 'index']);
            Route::post('/', [VendorTaxController::class, 'store']);
            Route::get('/{id}', [VendorTaxController::class, 'show']);
            Route::post('/{id}', [VendorTaxController::class, 'update']);
            Route::delete('/{id}', [VendorTaxController::class, 'destroy']);
            Route::get('/{id}/file', [VendorTaxController::class, 'downloadFile']);
            Route::get('/{id}/download-zip', [VendorTaxController::class, 'downloadZip']);
        });

        Route::prefix('legal')->group(function () {
            Route::get('/', [VendorLegalController::class, 'index']);
            Route::post('/', [VendorLegalController::class, 'store']);
            Route::get('/{id}', [VendorLegalController::class, 'show']);
            Route::post('/{id}', [VendorLegalController::class, 'update']);
            Route::delete('/{id}', [VendorLegalController::class, 'destroy']);
            Route::get('/{id}/file/{type}', [VendorLegalController::class, 'downloadFile']);
            Route::get('/{id}/download-zip', [VendorLegalController::class, 'downloadZip']);
        });

        Route::prefix('license')->group(function () {
            Route::get('/types', [VendorLicenseController::class, 'types']);
            Route::get('/', [VendorLicenseController::class, 'index']);
            Route::post('/', [VendorLicenseController::class, 'store']);
            Route::get('/{id}', [VendorLicenseController::class, 'show']);
            Route::post('/{id}', [VendorLicenseController::class, 'update']);
            Route::delete('/{id}', [VendorLicenseController::class, 'destroy']);
            Route::get('/{id}/file', [VendorLicenseController::class, 'downloadFile']);
            Route::get('/{id}/download-zip', [VendorLicenseController::class, 'downloadZip']);
        });

        Route::prefix('equipment')->group(function () {
            Route::get('/conditions', [VendorEquipmentController::class, 'conditions']);
            Route::get('/', [VendorEquipmentController::class, 'index']);
            Route::post('/', [VendorEquipmentController::class, 'store']);
            Route::get('/{id}', [VendorEquipmentController::class, 'show']);
            Route::post('/{id}', [VendorEquipmentController::class, 'update']);
            Route::delete('/{id}', [VendorEquipmentController::class, 'destroy']);
            Route::get('/{id}/file', [VendorEquipmentController::class, 'downloadFile']);
        });
    });
});
