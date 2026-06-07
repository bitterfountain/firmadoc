<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Portada publica: usuarios autenticados van a sus documentos; invitados ven las dos puertas. */
    public function index(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('documents.index');
        }

        return view('welcome');
    }
}
