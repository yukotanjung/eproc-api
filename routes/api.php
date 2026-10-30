<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordForgotController;
use App\Http\Controllers\PasswordActivateController;
use App\Http\Controllers\FundingSourceController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\BusinessTypeController;
use App\Http\Controllers\WorkUnitController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\MajorController;
use App\Http\Controllers\JobTypeController;
use App\Http\Controllers\JobFieldController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\ProcurementMethodController;
use App\Http\Controllers\ProcurementMethodStageController;
use App\Http\Controllers\ProcurementController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']); // Login account
    Route::get('/me', [AuthController::class, 'me']); // Get Profile
    Route::post('/activate', [PasswordActivateController::class, 'activateAccount']); // Get Profile
    Route::post('/activate/check_token', [PasswordActivateController::class, 'checkToken']);
    Route::get('/logout', [UserController::class, 'logout'])->middleware('auth:api'); // Logout account

    Route::post('/verify-email', [PasswordActivateController::class, 'verifyEmail']);

    Route::group(['prefix' => 'forgot_password'], function () {
        Route::post('/send', [PasswordForgotController::class, 'sendLink']);
        Route::post('/check_token', [PasswordForgotController::class, 'checkToken']);
        Route::post('/update_password', [PasswordForgotController::class, 'updatePassword']);
    });
});


Route::middleware(['auth:api', 'throttle:600,1'])->group(function () {

    Route::prefix('permission')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permission_read'); // List permission
        Route::get('/group', [PermissionController::class, 'group'])->middleware('permission:permission_read'); // List group permission
        Route::get('/{id}', [PermissionController::class, 'show'])->middleware('permission:permission_read'); // Show permission by id

    });

    Route::prefix('role')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('permission:role_read'); // List role
        Route::get('/{id}', [RoleController::class, 'show'])->middleware('permission:role_read'); // Show role by id
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:role_create'); // Add new role
        Route::post('/{id}', [RoleController::class, 'update'])->middleware('permission:role_update'); // Update role by id
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:role_delete'); // Delete role by id

    });

    Route::prefix('user')->group(function () {
        Route::get('/{user}/resend-activation', [UserController::class, 'resendActivation']); // Show user by id
        Route::get('/', [UserController::class, 'index'])->middleware('permission:user_read'); // List of user
        Route::post('/', [UserController::class, 'store'])->middleware('permission:user_create'); // List of user
        Route::post('/{id}', [UserController::class, 'update'])->middleware('permission:user_update'); // Update of user
        Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:user_read'); // Show user by id
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:user_delete'); // Delete user by id

    });

    Route::prefix('funding-source')->group(function () {
        Route::get('/', [FundingSourceController::class, 'index'])->middleware('permission:funding_source_read');
        Route::get('/{id}', [FundingSourceController::class, 'show'])->middleware('permission:funding_source_read');
        Route::post('/', [FundingSourceController::class, 'store'])->middleware('permission:funding_source_create');
        Route::post('/{id}', [FundingSourceController::class, 'update'])->middleware('permission:funding_source_update');
        Route::delete('/{id}', [FundingSourceController::class, 'destroy'])->middleware('permission:funding_source_delete');
    });

    Route::prefix('tax')->group(function () {
        Route::get('/', [TaxController::class, 'index'])->middleware('permission:tax_read');
        Route::get('/{id}', [TaxController::class, 'show'])->middleware('permission:tax_read');
        Route::post('/', [TaxController::class, 'store'])->middleware('permission:tax_create');
        Route::post('/{id}', [TaxController::class, 'update'])->middleware('permission:tax_update');
        Route::delete('/{id}', [TaxController::class, 'destroy'])->middleware('permission:tax_delete');
    });

    Route::prefix('business-type')->group(function () {
        Route::get('/', [BusinessTypeController::class, 'index'])->middleware('permission:business_type_read');
        Route::get('/{id}', [BusinessTypeController::class, 'show'])->middleware('permission:business_type_read');
        Route::post('/', [BusinessTypeController::class, 'store'])->middleware('permission:business_type_create');
        Route::post('/{id}', [BusinessTypeController::class, 'update'])->middleware('permission:business_type_update');
        Route::delete('/{id}', [BusinessTypeController::class, 'destroy'])->middleware('permission:business_type_delete');
    });

    Route::prefix('work-unit')->group(function () {
        Route::get('/', [WorkUnitController::class, 'index'])->middleware('permission:work_unit_read');
        Route::get('/{id}', [WorkUnitController::class, 'show'])->middleware('permission:work_unit_read');
        Route::post('/', [WorkUnitController::class, 'store'])->middleware('permission:work_unit_create');
        Route::post('/{id}', [WorkUnitController::class, 'update'])->middleware('permission:work_unit_update');
        Route::delete('/{id}', [WorkUnitController::class, 'destroy'])->middleware('permission:work_unit_delete');
    });

    Route::prefix('qualification')->group(function () {
        Route::get('/', [QualificationController::class, 'index'])->middleware('permission:qualification_read');
        Route::get('/{id}', [QualificationController::class, 'show'])->middleware('permission:qualification_read');
        Route::post('/', [QualificationController::class, 'store'])->middleware('permission:qualification_create');
        Route::post('/{id}', [QualificationController::class, 'update'])->middleware('permission:qualification_update');
        Route::delete('/{id}', [QualificationController::class, 'destroy'])->middleware('permission:qualification_delete');
    });

    Route::prefix('education-level')->group(function () {
        Route::get('/', [EducationLevelController::class, 'index'])->middleware('permission:education_level_read');
        Route::get('/{id}', [EducationLevelController::class, 'show'])->middleware('permission:education_level_read');
        Route::post('/', [EducationLevelController::class, 'store'])->middleware('permission:education_level_create');
        Route::post('/{id}', [EducationLevelController::class, 'update'])->middleware('permission:education_level_update');
        Route::delete('/{id}', [EducationLevelController::class, 'destroy'])->middleware('permission:education_level_delete');
    });

    Route::prefix('major')->group(function () {
        Route::get('/', [MajorController::class, 'index'])->middleware('permission:major_read');
        Route::get('/{id}', [MajorController::class, 'show'])->middleware('permission:major_read');
        Route::post('/', [MajorController::class, 'store'])->middleware('permission:major_create');
        Route::post('/{id}', [MajorController::class, 'update'])->middleware('permission:major_update');
        Route::delete('/{id}', [MajorController::class, 'destroy'])->middleware('permission:major_delete');
    });

    Route::prefix('job-type')->group(function () {
        Route::get('/', [JobTypeController::class, 'index'])->middleware('permission:job_type_read');
        Route::get('/{id}', [JobTypeController::class, 'show'])->middleware('permission:job_type_read');
        Route::post('/', [JobTypeController::class, 'store'])->middleware('permission:job_type_create');
        Route::post('/{id}', [JobTypeController::class, 'update'])->middleware('permission:job_type_update');
        Route::delete('/{id}', [JobTypeController::class, 'destroy'])->middleware('permission:job_type_delete');
    });

    Route::prefix('job-field')->group(function () {
        Route::get('/', [JobFieldController::class, 'index'])->middleware('permission:job_field_read');
        Route::get('/{id}', [JobFieldController::class, 'show'])->middleware('permission:job_field_read');
        Route::post('/', [JobFieldController::class, 'store'])->middleware('permission:job_field_create');
        Route::post('/{id}', [JobFieldController::class, 'update'])->middleware('permission:job_field_update');
        Route::delete('/{id}', [JobFieldController::class, 'destroy'])->middleware('permission:job_field_delete');
    });

    Route::prefix('classification')->group(function () {
        Route::get('/', [ClassificationController::class, 'index'])->middleware('permission:classification_read');
        Route::get('/{id}', [ClassificationController::class, 'show'])->middleware('permission:classification_read');
        Route::post('/', [ClassificationController::class, 'store'])->middleware('permission:classification_create');
        Route::post('/{id}', [ClassificationController::class, 'update'])->middleware('permission:classification_update');
        Route::delete('/{id}', [ClassificationController::class, 'destroy'])->middleware('permission:classification_delete');
    });

    Route::prefix('procurement-method')->group(function () {
        Route::get('/', [ProcurementMethodController::class, 'index'])->middleware('permission:procurement_method_read');
        Route::get('/{id}', [ProcurementMethodController::class, 'show'])->middleware('permission:procurement_method_read');
        Route::post('/', [ProcurementMethodController::class, 'store'])->middleware('permission:procurement_method_create');
        Route::post('/{id}', [ProcurementMethodController::class, 'update'])->middleware('permission:procurement_method_update');
        Route::delete('/{id}', [ProcurementMethodController::class, 'destroy'])->middleware('permission:procurement_method_delete');

        Route::get('/{id}/stage', [ProcurementMethodStageController::class, 'index'])->middleware('permission:procurement_method_read');
        Route::post('/{id}/stage', [ProcurementMethodStageController::class, 'store'])->middleware('permission:procurement_method_create');
        // static segment before the dynamic one, otherwise "reorder" is read as a stage id
        Route::post('/{id}/stage/reorder', [ProcurementMethodStageController::class, 'reorder'])->middleware('permission:procurement_method_update');
        Route::post('/{id}/stage/{stageId}', [ProcurementMethodStageController::class, 'update'])->middleware('permission:procurement_method_update');
        Route::delete('/{id}/stage/{stageId}', [ProcurementMethodStageController::class, 'destroy'])->middleware('permission:procurement_method_delete');
        Route::post('/{id}/preview-schedule', [ProcurementMethodStageController::class, 'previewSchedule'])->middleware('permission:procurement_method_read');
    });

    Route::prefix('procurement')->group(function () {
        Route::get('/', [ProcurementController::class, 'index'])->middleware('permission:procurement_read');
        Route::get('/{id}', [ProcurementController::class, 'show'])->middleware('permission:procurement_read');
        Route::post('/', [ProcurementController::class, 'store'])->middleware('permission:procurement_create');
        Route::post('/{id}', [ProcurementController::class, 'update'])->middleware('permission:procurement_update');
        Route::delete('/{id}', [ProcurementController::class, 'destroy'])->middleware('permission:procurement_delete');
        Route::post('/{id}/cancel', [ProcurementController::class, 'cancel'])->middleware('permission:procurement_cancel');
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::post('/update-password', [UserController::class, 'updatePassword']);
        Route::post('/update-profile', [UserController::class, 'updateProfile']);
        Route::post('/update-notification-device', [UserController::class, 'updateNotificationDevice']);
    });
});
