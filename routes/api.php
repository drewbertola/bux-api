<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LineItemController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UtilityController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;

//public routes
Route::post('/login', [AuthController::class, 'login'])
    ->middleware(ThrottleRequests::using('login'));
Route::post('/register', [AuthController::class, 'register'])
    ->middleware(ThrottleRequests::using('login'));
Route::post('/forgot', [AuthController::class, 'passwordResetCode'])
    ->middleware(ThrottleRequests::using('login'));
Route::post('/update-password', [AuthController::class, 'changePassword'])
    ->middleware(ThrottleRequests::using('login'));

Route::get('/whoami', [AuthController::class, 'whoami']);

// passkey login (public, mirrors password login's throttling)
Route::get('/webauthn/login/options', [PasskeyLoginController::class, 'index'])
    ->middleware(ThrottleRequests::using('login'));
Route::post('/webauthn/login', [PasskeyLoginController::class, 'store'])
    ->middleware(ThrottleRequests::using('login'));


// protected routes
Route::group(['middleware' => ['auth:sanctum']], function() {
    Route::get('/payment/customer/{customerId}', [PaymentController::class, 'customer']);
    Route::get('/invoice/customer/{customerId}', [InvoiceController::class, 'customer']);

    Route::get('/customer/tabledata', [CustomerController::class, 'getTableData']);
    Route::get('/customer/balance/{customerId}', [CustomerController::class, 'getBalanceData']);
    Route::get('/customer/{id}', [CustomerController::class, 'get']);
    Route::post('/customer/save', [CustomerController::class, 'save']);

    Route::get('/invoice', [InvoiceController::class, 'index']);
    Route::get('/invoice/{id}', [InvoiceController::class, 'get']);
    Route::post('/invoice/save', [InvoiceController::class, 'save']);
    Route::get('/invoice/sent/{id}', [InvoiceController::class, 'toggleSent']);

    Route::get('/payment', [PaymentController::class, 'index']);
    Route::get('/payment/{id}', [PaymentController::class, 'get']);
    Route::post('/payment/save', [PaymentController::class, 'save']);

    Route::get('/line_item/invoice/{invoiceId}', [LineItemController::class, 'index']);
    Route::get('/line_item/{id}', [LineItemController::class, 'get']);
    Route::post('/line_item/save', [LineItemController::class, 'save']);

    Route::get('/completions', [UtilityController::class, 'completions']);
    //Route::get('/line_item', LineItemController::class)->except(['edit', 'create']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/webauthn/passkeys', [PasskeyController::class, 'index']);
    Route::get('/webauthn/register/options', [PasskeyRegistrationController::class, 'index']);
    Route::post('/webauthn/register', [PasskeyRegistrationController::class, 'store']);
    Route::delete('/webauthn/passkeys/{passkey}', [PasskeyRegistrationController::class, 'destroy']);
});
