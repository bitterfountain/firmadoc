@extends('layouts.app')

@section('title', __('Entrar') . ' · FirmaDoc')

@section('content')
    <div class="mx-auto mt-10 max-w-sm">
        <div class="card p-8">
            <p class="eyebrow">{{ __('Acceso') }}</p>
            <h1 class="mt-2 text-2xl text-ink">{{ __('Entrar') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ __('Accede para gestionar tus documentos.') }}</p>

            @if ($errors->any())
                <div class="mt-5 rounded-xl px-3.5 py-2.5 text-sm" style="background:var(--color-danger-soft);color:var(--color-danger)">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-faint">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-faint">{{ __('Contraseña') }}</label>
                    <input id="password" name="password" type="password" required class="input">
                </div>
                <label class="flex items-center gap-2 text-sm text-muted">
                    <input type="checkbox" name="remember" class="size-4 rounded border-line-strong text-accent focus:ring-accent">
                    {{ __('Recordarme') }}
                </label>
                <button type="submit" class="btn btn-primary w-full">{{ __('Entrar') }}</button>
            </form>
        </div>

        <p class="mt-5 text-center text-sm text-muted">
            {{ __('¿No tienes cuenta?') }}
            <a href="{{ route('pro.request') }}" class="font-semibold text-accent hover:underline">{{ __('Solicita acceso Pro') }}</a>
        </p>
    </div>
@endsection
