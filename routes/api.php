<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\PropertyMemberController;
use App\Http\Controllers\Api\RentalAgreementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'message' => 'API läuft',
    ]);
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('addresses', AddressController::class);
    Route::apiResource('properties', PropertyController::class);
    Route::put('/properties/{property}/members', [PropertyMemberController::class, 'sync']);
    Route::apiResource('rental-agreements', RentalAgreementController::class);
});
