<?php

namespace App\Http\Controllers;

use App\Models\AccountInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminInviteController extends Controller
{
    /** Genera un enlace de invitación (solo administradores). */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $invite = AccountInvite::generate(365, 30, $request->user()->id);

        return back()->with('invite_url', $invite->url());
    }
}
