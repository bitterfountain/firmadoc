@extends('layouts.app')

@section('title', __('Mi certificado') . ' · FirmaDoc')

@section('content')
    <div class="mx-auto max-w-lg">
        <a href="{{ route('documents.index') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            {{ __('Volver') }}
        </a>

        <p class="eyebrow mt-4">{{ __('Firma avanzada') }}</p>
        <h1 class="mt-1 text-2xl text-ink">{{ __('Mi certificado de firma') }}</h1>
        <p class="mt-1.5 text-sm leading-relaxed text-muted">
            {{ __('Sube tu certificado de empresa (.p12/.pfx) para sellar tus PDF con él. Sin certificado propio, las firmas usan uno autofirmado (válido pero "no de confianza" en los visores).') }}
        </p>

        @if (auth()->user()->hasSigningCert())
            @php $u = auth()->user(); $expired = $u->signing_cert_expires_at && $u->signing_cert_expires_at->isPast(); @endphp
            <div class="card mt-5 p-5">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9.5 12l1.8 1.8L15 10"/></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="eyebrow">{{ __('Certificado activo') }}</p>
                        <p class="mt-0.5 truncate font-semibold text-ink">{{ $u->signing_cert_subject }}</p>
                        <p class="mt-0.5 text-xs text-faint">{{ $u->signing_cert_name }}</p>
                        @if ($u->signing_cert_expires_at)
                            <p class="mt-1 text-xs {{ $expired ? '' : 'text-muted' }}" @if($expired) style="color:var(--color-danger)" @endif>
                                {{ $expired ? __('Caducado el') : __('Válido hasta') }} {{ $u->signing_cert_expires_at->format('d/m/Y') }}
                            </p>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('cert.destroy') }}" class="mt-4" onsubmit="return confirm('{{ __('¿Eliminar tu certificado?') }}')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger px-4 py-1.5 text-xs">{{ __('Eliminar certificado') }}</button>
                </form>
            </div>
        @endif

        <div class="card mt-5 p-6">
            <h2 class="text-sm font-semibold text-ink">{{ auth()->user()->hasSigningCert() ? __('Reemplazar certificado') : __('Subir certificado') }}</h2>

            @if ($errors->any())
                <div class="mt-3 rounded-xl px-3.5 py-2.5 text-sm" style="background:var(--color-danger-soft);color:var(--color-danger)">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('cert.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input type="file" name="certificate" accept=".p12,.pfx" required
                       class="w-full text-sm text-muted file:mr-3 file:rounded-full file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-paper">
                <input type="password" name="password" placeholder="{{ __('Contraseña del certificado') }}" class="input">
                <button type="submit" class="btn btn-primary w-full">{{ __('Guardar certificado') }}</button>
            </form>
            <p class="mt-3 text-xs leading-relaxed text-faint">
                {{ __('El certificado y su contraseña se guardan cifrados y solo se usan para sellar tus firmas.') }}
            </p>
        </div>
    </div>
@endsection
