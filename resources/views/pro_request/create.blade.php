@extends('layouts.app')

@section('title', __('Solicitar acceso Pro') . ' · FirmaDoc')

@section('content')
    <div class="mx-auto mt-8 max-w-md">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            {{ __('Entrar') }}
        </a>

        <div class="card mt-3 p-8">
            <p class="eyebrow">{{ __('Cuenta profesional') }}</p>
            <h1 class="mt-2 text-2xl text-ink">{{ __('Solicitar acceso Pro') }}</h1>
            <p class="mt-1.5 text-sm leading-relaxed text-muted">
                {{ __('Déjanos tu email y te enviaremos una invitación para activar tu cuenta profesional.') }}
            </p>

            @if ($errors->any())
                <div class="mt-5 rounded-xl px-3.5 py-2.5 text-sm" style="background:var(--color-danger-soft);color:var(--color-danger)">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('pro.request.store') }}" class="mt-6 space-y-3">
                @csrf
                <input name="name" type="text" placeholder="{{ __('Nombre (opcional)') }}" value="{{ old('name') }}" class="input">
                <input name="email" type="email" placeholder="{{ __('tu@email.com') }}" value="{{ old('email') }}" required autofocus class="input">
                <textarea name="message" rows="3" placeholder="{{ __('¿Para qué lo necesitas? (opcional)') }}" class="input">{{ old('message') }}</textarea>
                <button type="submit" class="btn btn-primary w-full">{{ __('Solicitar invitación') }}</button>
            </form>
        </div>
    </div>
@endsection
