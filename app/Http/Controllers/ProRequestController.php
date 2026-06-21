<?php

namespace App\Http\Controllers;

use App\Models\AccountInvite;
use App\Models\ProRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class ProRequestController extends Controller
{
    /** Formulario público para solicitar acceso Pro. */
    public function create(): View
    {
        return view('pro_request.create');
    }

    /** Guarda la solicitud y avisa al administrador. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'email' => 'required|email|max:190',
            'message' => 'nullable|string|max:500',
        ]);

        ProRequest::create($data + ['status' => 'pending']);

        try {
            $body = "Nueva solicitud de cuenta Pro:\n{$data['email']}"
                .(! empty($data['name']) ? " ({$data['name']})" : '')
                .(! empty($data['message']) ? "\n\n{$data['message']}" : '');
            Mail::raw($body, fn ($m) => $m->to('info@leukasoft.com')->subject('FirmaDoc · solicitud de cuenta Pro'));
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('login')
            ->with('status', __('Gracias, hemos recibido tu solicitud. Te enviaremos una invitación pronto.'));
    }

    /** El admin invita a quien lo solicitó: genera el enlace y se lo envía por email. */
    public function invite(Request $request, ProRequest $proRequest): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $invite = AccountInvite::generate(365, 30, $request->user()->id);
        $proRequest->update(['status' => 'invited']);

        $sent = false;
        try {
            $url = $invite->url();
            Mail::raw(
                "Te invitamos a activar tu cuenta profesional de FirmaDoc (gratis, 1 año):\n{$url}",
                fn ($m) => $m->to($proRequest->email, $proRequest->name)
                    ->subject('Tu invitación a FirmaDoc Pro')
            );
            $sent = true;
        } catch (Throwable $e) {
            report($e);
        }

        return back()
            ->with('invite_url', $invite->url())
            ->with('status', $sent ? __('Invitación enviada a :email.', ['email' => $proRequest->email]) : null);
    }
}
