<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;
use App\Http\Controllers\AdminController;

Route::middleware('check.admin.host')->group(function () {
    Route::post('/hefesto/api',[AdminController::class,'postApi']);
    Route::get('/hefesto/api/{key}',[AdminController::class,'getApi']);
    Route::put('/hefesto/api/{key}',[AdminController::class,'putApi']);
    Route::get('/hefesto/api',[AdminController::class,'getApis']);
});

Route::any('{any}',[
    MainController::class, 'execute'
])->where('any', '.*')->middleware('public');


