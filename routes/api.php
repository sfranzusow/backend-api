<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentLayoutTemplateController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\DocumentTemplatePlaceholderController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\PropertyMemberController;
use App\Http\Controllers\Api\ReminderController;
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
    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('addresses', AddressController::class);
    Route::apiResource('bank-accounts', BankAccountController::class);
    Route::apiResource('properties', PropertyController::class);
    Route::put('/properties/{property}/members', [PropertyMemberController::class, 'sync']);
    Route::apiResource('rental-agreements', RentalAgreementController::class);
    Route::get('/document-template-placeholders', DocumentTemplatePlaceholderController::class);
    Route::apiResource('document-templates', DocumentTemplateController::class);
    Route::post('/document-templates/{document_template}/activate', [DocumentTemplateController::class, 'activate']);
    Route::apiResource('document-layout-templates', DocumentLayoutTemplateController::class);
    Route::post('/document-layout-templates/{document_layout_template}/activate', [DocumentLayoutTemplateController::class, 'activate']);
    Route::get('/rental-agreements/{rental_agreement}/documents', [RentalAgreementDocumentController::class, 'index']);
    Route::post('/rental-agreements/{rental_agreement}/documents', [RentalAgreementDocumentController::class, 'store']);
    Route::get('/rental-agreements/{rental_agreement}/payments', [PaymentController::class, 'index']);
    Route::post('/rental-agreements/{rental_agreement}/payments', [PaymentController::class, 'store']);
    Route::get('/rental-agreements/{rental_agreement}/reminders', [ReminderController::class, 'indexForRentalAgreement']);
    Route::post('/rental-agreements/{rental_agreement}/reminders', [ReminderController::class, 'storeForRentalAgreement']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::post('/documents/{document}/generate', [DocumentController::class, 'generate']);
    Route::post('/documents/{document}/share', [DocumentController::class, 'share']);
    Route::post('/documents/{document}/void', [DocumentController::class, 'voidDocument']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::post('/documents/{document}/signed-upload', [DocumentController::class, 'signedUpload']);
    Route::get('/documents/{document}/signed-download', [DocumentController::class, 'signedDownload']);
    Route::get('/documents/{document}/reminders', [ReminderController::class, 'indexForDocument']);
    Route::post('/documents/{document}/reminders', [ReminderController::class, 'storeForDocument']);
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::get('/reminders/summary', [ReminderController::class, 'summary']);
    Route::patch('/reminders/{reminder}', [ReminderController::class, 'update']);
    Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::patch('/payments/{payment}', [PaymentController::class, 'update']);
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
    Route::get('/payments/{payment}/reminders', [ReminderController::class, 'indexForPayment']);
    Route::post('/payments/{payment}/reminders', [ReminderController::class, 'storeForPayment']);
});
