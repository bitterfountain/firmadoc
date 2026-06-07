@extends('layouts.app')

@section('title', 'FirmaDoc · Firma de documentos')

@section('content')
    <div class="mx-auto mt-8 max-w-3xl text-center">
        <h1 class="text-2xl font-bold text-slate-900">Firma documentos online</h1>
        <p class="mt-2 text-slate-500">Elige cómo quieres firmar.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            {{-- Puerta 1: firma rápida anónima --}}
            <a href="{{ route('quick.start') }}"
               class="group rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:border-indigo-300 hover:shadow">
                <div class="grid size-10 place-items-center rounded-xl bg-indigo-600 text-white">⚡</div>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">Firma rápida</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Sin cuenta. Sube, firma con verificación por email y recibe el PDF.
                    <span class="text-slate-400">No se guarda nada en el servidor.</span>
                </p>
                <span class="mt-4 inline-block text-sm font-semibold text-indigo-600 group-hover:underline">Firmar ahora →</span>
            </a>

            {{-- Puerta 2: cuenta --}}
            <a href="{{ route('login') }}"
               class="group rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:border-indigo-300 hover:shadow">
                <div class="grid size-10 place-items-center rounded-xl bg-slate-800 text-white">🔒</div>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">Entrar</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Para firma avanzada (PAdES criptográfico, multi-firmante) y guardar tus documentos.
                </p>
                <span class="mt-4 inline-block text-sm font-semibold text-slate-700 group-hover:underline">Acceder →</span>
            </a>
        </div>
    </div>
@endsection
