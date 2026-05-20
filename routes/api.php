<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentReminderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\PropertyMemberController;
use App\Http\Controllers\Api\RentalAgreementController;
use App\Http\Controllers\Api\RentalAgreementDocumentController;
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
    Route::get('/rental-agreements/{rental_agreement}/documents', [RentalAgreementDocumentController::class, 'index']);
    Route::post('/rental-agreements/{rental_agreement}/documents', [RentalAgreementDocumentController::class, 'store']);
    Route::get('/rental-agreements/{rental_agreement}/payments', [PaymentController::class, 'index']);
    Route::post('/rental-agreements/{rental_agreement}/payments', [PaymentController::class, 'store']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::post('/documents/{document}/generate', [DocumentController::class, 'generate']);
    Route::post('/documents/{document}/share', [DocumentController::class, 'share']);
    Route::post('/documents/{document}/void', [DocumentController::class, 'voidDocument']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::post('/documents/{document}/signed-upload', [DocumentController::class, 'signedUpload']);
    Route::get('/documents/{document}/signed-download', [DocumentController::class, 'signedDownload']);
    Route::get('/documents/{document}/reminders', [DocumentReminderController::class, 'index']);
    Route::post('/documents/{document}/reminders', [DocumentReminderController::class, 'store']);
    Route::patch('/document-reminders/{document_reminder}', [DocumentReminderController::class, 'update']);
    Route::delete('/document-reminders/{document_reminder}', [DocumentReminderController::class, 'destroy']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::patch('/payments/{payment}', [PaymentController::class, 'update']);
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
});
