<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso a la zona privada si la cuenta profesional ha caducado
 * (pro_until en el pasado). Las cuentas con pro_until = NULL no caducan.
 */
class EnsureProActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->proActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta profesional ha caducado. Solicita una renovación.',
            ]);
        }

        return $next($request);
    }
}
