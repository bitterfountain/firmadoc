<?php

use App\Http\Controllers\AdminInviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProRequestController;
use App\Http\Controllers\QuickSignController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\VisitAnalyticsController;
use App\Http\Middleware\EnsureProActive;
use Illuminate\Support\Facades\Route;

// Portada publica (dos puertas: firma rapida / entrar).
Route::get('/', [HomeController::class, 'index'])->middleware('track:home')->name('home');

// Cambio de idioma (es / en).
Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Paginas legales (publicas).
Route::view('/aviso-legal', 'legal.aviso')->middleware('track:legal')->name('legal.aviso');
Route::view('/privacidad', 'legal.privacidad')->middleware('track:legal')->name('legal.privacy');

// Solicitar acceso Pro dejando el email (publico).
Route::get('/solicitar-pro', [ProRequestController::class, 'create'])->middleware('track:pro_request')->name('pro.request');
Route::post('/solicitar-pro', [ProRequestController::class, 'store'])->middleware('throttle:6,1')->name('pro.request.store');

// Invitaciones de un solo uso -> cuenta profesional gratis (publico).
Route::controller(InviteController::class)->group(function () {
    Route::get('/invitacion/{token}', 'show')->whereAlphaNumeric('token')->middleware('track:invite')->name('invite.show');
    Route::post('/invitacion/{token}', 'register')->whereAlphaNumeric('token')->middleware('throttle:10,1')->name('invite.register');
});

// --- Autenticacion (registro cerrado: usuarios via `php artisan firmadoc:user`) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->middleware('track:login')->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// --- Firma rapida ANONIMA y efimera (sin login) ---
Route::controller(QuickSignController::class)->prefix('firmar')->name('quick.')->group(function () {
    Route::get('/', 'start')->middleware('track:quick')->name('start');
    Route::post('/', 'upload')->middleware('throttle:10,1')->name('upload');
    Route::get('/{eid}', 'sign')->whereAlphaNumeric('eid')->middleware('track:sign')->name('sign');
    Route::get('/{eid}/pdf', 'pdf')->whereAlphaNumeric('eid')->name('pdf');
    Route::post('/{eid}/signed', 'finalize')->whereAlphaNumeric('eid')->name('finalize');
    Route::get('/{eid}/descargar', 'download')->whereAlphaNumeric('eid')->name('download');
});

// --- Zona privada del propietario (requiere login + cuenta Pro vigente) ---
Route::middleware(['auth', EnsureProActive::class])->group(function () {
    Route::post('/invitaciones', [AdminInviteController::class, 'store'])->name('invites.store');
    Route::post('/solicitudes/{proRequest}/invitar', [ProRequestController::class, 'invite'])->name('pro.request.invite');
    Route::get('/admin/visitas', [VisitAnalyticsController::class, 'index'])->name('admin.visits');

    Route::controller(DocumentController::class)->prefix('documents')->name('documents.')->group(function () {
        Route::get('/', 'index')->middleware('track:app')->name('index');
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
