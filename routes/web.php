<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\QuickSignController;
use App\Http\Controllers\SignatureController;
use Illuminate\Support\Facades\Route;

// Portada publica (dos puertas: firma rapida / entrar).
Route::get('/', [HomeController::class, 'index'])->name('home');

// --- Autenticacion (registro cerrado: usuarios via `php artisan firmadoc:user`) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// --- Firma rapida ANONIMA y efimera (sin login) ---
Route::controller(QuickSignController::class)->prefix('firmar')->name('quick.')->group(function () {
    Route::get('/', 'start')->name('start');
    Route::post('/', 'upload')->middleware('throttle:10,1')->name('upload');
    Route::get('/{eid}', 'sign')->whereAlphaNumeric('eid')->name('sign');
    Route::get('/{eid}/pdf', 'pdf')->whereAlphaNumeric('eid')->name('pdf');
    Route::post('/{eid}/otp', 'otp')->whereAlphaNumeric('eid')->middleware('throttle:6,1')->name('otp');
    Route::post('/{eid}/otp/verify', 'otpVerify')->whereAlphaNumeric('eid')->middleware('throttle:10,1')->name('otpVerify');
    Route::post('/{eid}/signed', 'finalize')->whereAlphaNumeric('eid')->name('finalize');
    Route::get('/{eid}/descargar', 'download')->whereAlphaNumeric('eid')->name('download');
});

// --- Zona privada del propietario (requiere login) ---
Route::middleware('auth')->group(function () {
    Route::controller(DocumentController::class)->prefix('documents')->name('documents.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{document}/sign', 'sign')->name('sign');
        Route::get('/{document}/audit', 'audit')->name('audit');
        Route::get('/{document}/pdf', 'pdf')->name('pdf');
        Route::get('/{document}/download', 'download')->name('download');
        Route::delete('/{document}', 'destroy')->name('destroy');
    });

    // Firma propia con verificacion OTP (firmante unico, Nivel 1).
    Route::controller(SignatureController::class)->prefix('documents/{document}')->name('documents.')->group(function () {
        Route::post('/otp', 'requestOtp')->middleware('throttle:6,1')->name('otp');
        Route::post('/otp/verify', 'verifyOtp')->middleware('throttle:10,1')->name('otpVerify');
        Route::post('/signed', 'finalize')->name('storeSigned');
    });

    // Multi-firmante: gestion de firmantes (propietario).
    Route::controller(InvitationController::class)->prefix('documents/{document}')->name('documents.')->group(function () {
        Route::get('/signers', 'index')->name('signers');
        Route::post('/invitations', 'store')->name('invitations.store');
        Route::delete('/invitations/{invitation}', 'destroy')->name('invitations.destroy');
    });
});

// --- Multi-firmante: flujo publico de firma por token (sin login) ---
Route::controller(InvitationController::class)->prefix('sign/{token}')->name('sign.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::get('/pdf', 'pdf')->name('pdf');
    Route::post('/otp', 'requestOtp')->middleware('throttle:6,1')->name('otp');
    Route::post('/otp/verify', 'verifyOtp')->middleware('throttle:10,1')->name('otpVerify');
    Route::post('/signed', 'finalize')->name('finalize');
});
