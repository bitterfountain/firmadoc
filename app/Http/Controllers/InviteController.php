<?php

namespace App\Http\Controllers;

use App\Models\AccountInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Canje de enlaces de invitación de un solo uso → cuenta profesional gratis.
 */
class InviteController extends Controller
{
    private function find(string $token): ?AccountInvite
    {
        return AccountInvite::where('token', $token)->first();
    }

    /** Landing de invitación (estilo "te han invitado") con el formulario de alta. */
    public function show(string $token): View
    {
        $invite = $this->find($token);

        if (! $invite || ! $invite->isUsable()) {
            return view('invite.invalid');
        }

        return view('invite.show', ['invite' => $invite]);
    }

    /** Crea la cuenta profesional, marca el enlace como usado y entra. */
    public function register(Request $request, string $token): RedirectResponse
    {
        $invite = $this->find($token);
        abort_unless($invite && $invite->isUsable(), 410);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'pro_until' => now()->addDays($invite->grant_days),
            'is_admin' => false,
        ]);

        $invite->update(['used_at' => now(), 'used_by' => $user->id]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('documents.index')
            ->with('status', __('¡Bienvenido a FirmaDoc Pro! Tu cuenta profesional está activa.'));
    }
}
