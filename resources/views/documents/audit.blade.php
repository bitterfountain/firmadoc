@extends('layouts.app')

@section('title', __('Auditoría') . ' · FirmaDoc')

@section('content')
    <div class="mb-5">
        <a href="{{ route('documents.index') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            {{ __('Volver') }}
        </a>
        <h1 class="mt-1 text-xl text-ink">{{ __('Auditoría de firma') }}</h1>
        <p class="text-sm text-muted">{{ $document->original_name }}</p>
    </div>

    @php
        $statusStyle = fn (string $s) => match ($s) {
            'completed' => 'color:var(--color-accent);background:var(--color-accent-soft)',
            'verified' => 'color:var(--color-ink);background:rgba(28,25,19,0.06)',
            'expired' => 'color:var(--color-danger);background:var(--color-danger-soft)',
            default => 'color:var(--color-muted);background:rgba(28,25,19,0.05)',
        };
    @endphp

    @forelse ($events as $event)
        <div class="card mb-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-3">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-sm font-semibold text-ink">{{ $event->reference }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="{{ $statusStyle($event->status) }}">{{ __(ucfirst($event->status)) }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($event->pades_applied)
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-gold);background:rgba(176,135,56,0.12)">{{ __('PAdES · sellado criptográfico') }}</span>
                    @endif
                    @if ($event->verified_at)
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-accent);background:var(--color-accent-soft)">{{ __('Identidad verificada') }}</span>
                    @endif
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="eyebrow">{{ __('Firmante') }}</dt>
                    <dd class="mt-0.5 text-ink">{{ $event->signer_name }}</dd>
                </div>
                <div>
                    <dt class="eyebrow">{{ __('Email verificado') }}</dt>
                    <dd class="mt-0.5 text-ink">{{ $event->signer_email }}</dd>
                </div>
                <div>
                    <dt class="eyebrow">{{ __('Fecha de verificación') }}</dt>
                    <dd class="mt-0.5 text-ink">{{ $event->verified_at?->format('d/m/Y H:i:s') ?? '—' }} UTC</dd>
                </div>
                <div>
                    <dt class="eyebrow">{{ __('Dirección IP') }}</dt>
                    <dd class="mt-0.5 text-ink">{{ $event->ip_address ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="eyebrow">{{ __('Navegador') }}</dt>
                    <dd class="mt-0.5 truncate text-muted" title="{{ $event->user_agent }}">{{ $event->user_agent ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="eyebrow">{{ __('Hash SHA-256 documento (original)') }}</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs text-muted">{{ $event->original_sha256 ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="eyebrow">{{ __('Hash SHA-256 documento (firmado)') }}</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs text-muted">{{ $event->signed_sha256 ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    @empty
        <div class="card p-10 text-center text-sm text-muted">
            {{ __('Este documento aún no tiene eventos de firma.') }}
        </div>
    @endforelse
@endsection
