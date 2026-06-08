@extends('layouts.app')

@section('title', __('Invitación no válida') . ' · FirmaDoc')

@section('content')
    <div class="card mx-auto mt-10 max-w-md p-10 text-center">
        <div class="mx-auto mb-4 grid size-12 place-items-center rounded-full" style="background:var(--color-danger-soft);color:var(--color-danger)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-6"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        </div>
        <h1 class="text-xl text-ink">{{ __('Invitación no válida') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            {{ __('Este enlace ya se ha usado o ha caducado. Pide una invitación nueva a quien te lo envió.') }}
        </p>
        <a href="{{ url('/') }}" class="btn btn-ghost mt-6 px-5">{{ __('Ir al inicio') }}</a>
    </div>
@endsection
