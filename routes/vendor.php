<?php

use App\Http\Controllers\CityController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\Vendor\VendorAuthController;
use App\Http\Controllers\Vendor\VendorOwnerController;
use App\Http\Controllers\Vendor\VendorProfileController;
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
    });
});
