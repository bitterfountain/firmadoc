<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Gestión del certificado de firma propio del usuario (PAdES pkcs12).
 * El .p12/.pfx y su contraseña se guardan cifrados en el usuario.
 */
class SigningCertController extends Controller
{
    public function index(): View
    {
        return view('certificates.index', ['user' => auth()->user()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'certificate' => 'required|file|max:2048',
            'password' => 'nullable|string|max:200',
        ]);

        $file = $request->file('certificate');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['p12', 'pfx'], true)) {
            return back()->withErrors(['certificate' => __('El certificado debe ser un archivo .p12 o .pfx.')]);
        }

        $contents = file_get_contents($file->getRealPath());
        $password = (string) $request->input('password', '');

        $certs = [];
        if (! openssl_pkcs12_read($contents, $certs, $password)) {
            return back()->withErrors(['certificate' => __('No se pudo leer el certificado: contraseña incorrecta o formato no compatible (reexpórtalo con cifrado AES).')]);
        }

        $info = openssl_x509_parse($certs['cert'] ?? '') ?: [];
        $subject = $info['subject']['CN'] ?? ($info['name'] ?? 'Certificado');
        $expires = isset($info['validTo_time_t']) ? Carbon::createFromTimestamp($info['validTo_time_t']) : null;

        auth()->user()->update([
            'signing_cert' => base64_encode($contents),
            'signing_cert_password' => $password,
            'signing_cert_subject' => Str::limit($subject, 250, ''),
            'signing_cert_name' => $file->getClientOriginalName(),
            'signing_cert_expires_at' => $expires?->toDateString(),
        ]);

        return back()->with('status', __('Certificado guardado. Tus firmas usarán este certificado.'));
    }

    public function destroy(): RedirectResponse
    {
        auth()->user()->update([
            'signing_cert' => null,
            'signing_cert_password' => null,
            'signing_cert_subject' => null,
            'signing_cert_name' => null,
            'signing_cert_expires_at' => null,
        ]);

        return back()->with('status', __('Certificado eliminado.'));
    }
}
