@extends('layouts.app')

@section('title', __('Tu invitación') . ' · FirmaDoc Pro')

@section('content')
    @php
        $d = $invite->grant_days;
        if ($d % 365 === 0) {
            $n = intdiv($d, 365);
            $label = $n . ' ' . ($n > 1 ? __('años') : __('año'));
        } else {
            $label = $d . ' ' . __('días');
        }
    @endphp

    <div class="mx-auto mt-6 max-w-md text-center">
        <span class="brand-mark mx-auto size-14">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" class="size-7">
                <path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/>
                <path d="M12 7S10.5 3 8 3a2.5 2.5 0 0 0 0 5h4zM12 7s1.5-4 4-4a2.5 2.5 0 0 1 0 5h-4z"/>
            </svg>
        </span>

        <p class="eyebrow mt-6">{{ __('Invitación') }}</p>
        <h1 class="mt-2 text-3xl leading-tight text-ink">{{ __('Te han invitado a') }}<br><em class="font-normal italic text-accent">FirmaDoc&nbsp;Pro</em></h1>
        <p class="mx-auto mt-3 max-w-sm text-[15px] leading-relaxed text-muted">
            {!! __('Activa <strong class="text-ink">:label</strong> de cuenta profesional, <strong class="text-ink">gratis</strong>. Sin tarjeta, sin compromiso.', ['label' => $label]) !!}
        </p>

        <div class="card mt-7 p-6 text-left">
            <p class="eyebrow">{{ __('Incluye') }}</p>
            <ul class="mt-3 space-y-2 text-sm text-muted">
                @foreach ([
                    'Firma avanzada con sello criptográfico (PAdES)',
                    'Multi-firmante con orden y sello de tiempo',
                    'Tus documentos guardados y privados',
                    'Auditoría y página-certificado de cada firma',
                ] as $feat)
                    <li class="flex items-start gap-2.5">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-accent"><path d="M20 6 9 17l-5-5"/></svg>
                        {{ __($feat) }}
                    </li>
                @endforeach
            </ul>

            <hr class="my-6 border-line">

            @if ($errors->any())
                <div class="mb-4 rounded-xl px-3.5 py-2.5 text-sm" style="background:var(--color-danger-soft);color:var(--color-danger)">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('invite.register', $invite->token) }}" class="space-y-3">
                @csrf
                <input name="name" type="text" placeholder="{{ __('Nombre completo') }}" value="{{ old('name') }}" required autofocus class="input">
                <input name="email" type="email" placeholder="{{ __('tu@email.com') }}" value="{{ old('email') }}" required class="input">
                <input name="password" type="password" placeholder="{{ __('Contraseña (mín. 8)') }}" required class="input">
                <input name="password_confirmation" type="password" placeholder="{{ __('Repite la contraseña') }}" required class="input">
                <button type="submit" class="btn btn-primary w-full">{{ __('Activar mi cuenta Pro') }}</button>
            </form>
        </div>

        <p class="mt-5 text-xs text-faint">
            {{ __('La cuenta profesional será válida durante :label. Este enlace es de un solo uso.', ['label' => $label]) }}
        </p>
    </div>
@endsection
