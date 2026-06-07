@extends('layouts.app')

@section('title', 'Firma rápida · FirmaDoc')

@section('content')
    <div class="mx-auto mt-8 max-w-lg">
        <a href="{{ url('/') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Inicio</a>
        <div class="mt-2 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-xl font-semibold text-slate-900">Firma rápida</h1>
            <p class="mt-1 text-sm text-slate-500">
                Sube tu documento (PDF, Word o imagen) y fírmalo en el navegador.
                Opcional: deja tu email para recibir una copia. <strong>No guardamos nada en el servidor.</strong>
            </p>

            <form method="POST" action="{{ route('quick.upload') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center hover:border-indigo-400">
                    <span class="text-3xl">📄</span>
                    <span class="mt-2 text-sm font-medium text-slate-700">Pulsa para elegir un archivo</span>
                    <span class="mt-1 text-xs text-slate-400">PDF, DOCX, DOC, ODT, JPG, PNG</span>
                    <input type="file" name="file" required class="mt-3 text-xs"
                           accept=".pdf,.docx,.doc,.odt,.jpg,.jpeg,.png">
                </label>

                @error('file')
                    <p class="text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Subir y firmar
                </button>
            </form>
        </div>
        <p class="mt-4 text-center text-xs text-slate-400">
            ¿Necesitas firma criptográfica (PAdES) o varios firmantes?
            <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Entra con tu cuenta</a>.
        </p>
    </div>
@endsection
