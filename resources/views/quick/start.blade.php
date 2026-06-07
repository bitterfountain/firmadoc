@extends('layouts.app')

@section('title', 'Firma rápida · FirmaDoc')

@section('content')
    <div class="mx-auto mt-6 max-w-lg">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Inicio
        </a>

        <div class="card mt-3 p-8">
            <p class="eyebrow">Sin cuenta</p>
            <h1 class="mt-2 text-2xl text-ink">Firma rápida</h1>
            <p class="mt-1.5 text-sm leading-relaxed text-muted">
                Sube tu documento (PDF, Word o imagen) y fírmalo en el navegador.
                Opcional: deja tu email para recibir una copia.
                <span class="text-faint">No guardamos nada en el servidor.</span>
            </p>

            <form method="POST" action="{{ route('quick.upload') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf
                <label class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-line-strong bg-paper/40 px-4 py-10 text-center transition-colors hover:border-accent hover:bg-accent-soft/40">
                    <span class="grid size-12 place-items-center rounded-xl bg-accent-soft text-accent transition-transform group-hover:-translate-y-0.5">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/></svg>
                    </span>
                    <span class="mt-3 text-sm font-semibold text-ink">Pulsa para elegir un archivo</span>
                    <span class="mt-1 text-xs text-faint">PDF · DOCX · DOC · ODT · JPG · PNG</span>
                    <input type="file" name="file" required class="mt-3 text-xs text-muted file:mr-3 file:rounded-full file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-paper"
                           accept=".pdf,.docx,.doc,.odt,.jpg,.jpeg,.png">
                </label>

                @error('file')
                    <p class="text-sm" style="color:var(--color-danger)">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn btn-primary w-full">Subir y firmar</button>
            </form>
        </div>

        <p class="mt-5 text-center text-xs text-faint">
            ¿Necesitas firma criptográfica (PAdES) o varios firmantes?
            <a href="{{ route('login') }}" class="font-semibold text-accent hover:underline">Entra con tu cuenta</a>.
        </p>
    </div>
@endsection
