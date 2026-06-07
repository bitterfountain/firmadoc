<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\SignatureController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentController::class, 'index'])->name('documents.index');

Route::controller(DocumentController::class)->prefix('documents')->name('documents.')->group(function () {
    Route::post('/', 'store')->name('store');
    Route::get('/{document}/sign', 'sign')->name('sign');
    Route::get('/{document}/audit', 'audit')->name('audit');
    Route::get('/{document}/pdf', 'pdf')->name('pdf');
    Route::get('/{document}/download', 'download')->name('download');
    Route::delete('/{document}', 'destroy')->name('destroy');
});

// Flujo de firma con verificacion OTP (firmante unico, Nivel 1).
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

// Multi-firmante: flujo publico de firma por token (sin login).
Route::controller(InvitationController::class)->prefix('sign/{token}')->name('sign.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::get('/pdf', 'pdf')->name('pdf');
    Route::post('/otp', 'requestOtp')->middleware('throttle:6,1')->name('otp');
    Route::post('/otp/verify', 'verifyOtp')->middleware('throttle:10,1')->name('otpVerify');
    Route::post('/signed', 'finalize')->name('finalize');
});
