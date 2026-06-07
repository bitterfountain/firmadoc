<?php

namespace App\Providers;

use App\Mail\Transport\RelayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Transporte de correo "relay" (Resend casero por HTTPS).
        Mail::extend('relay', function (array $config) {
            return new RelayTransport(
                (string) ($config['url'] ?? ''),
                (string) ($config['token'] ?? ''),
                (int) ($config['timeout'] ?? 30),
            );
        });
    }
}
