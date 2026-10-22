<?php

use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;


Route::get('/file/{token}', [ToolController::class, 'getS3File'])->name("make.file");
Route::get('/photo/{token}', [ToolController::class, 'getS3Photo'])->name("make.photo");


Route::get('/', function () {
    return view('welcome');
});
