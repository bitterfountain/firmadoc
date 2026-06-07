@extends('layouts.app')

@section('title', 'Auditoria · FirmaDoc')

@section('content')
    <div class="mb-4">
        <a href="{{ route('documents.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Volver</a>
        <h1 class="text-lg font-semibold text-slate-900">Auditoria de firma</h1>
        <p class="text-sm text-slate-500">{{ $document->original_name }}</p>
    </div>

    @php
        $statusBadge = fn (string $s) => match ($s) {
            'completed' => 'bg-emerald-100 text-emerald-700',
            'verified' => 'bg-indigo-100 text-indigo-700',
            'expired' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    @forelse ($events as $event)
        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <span class="font-mono text-sm font-semibold text-slate-900">{{ $event->reference }}</span>
                    <span class="ml-2 rounded px-2 py-0.5 text-[11px] font-semibold {{ $statusBadge($event->status) }}">
                        {{ ucfirst($event->status) }}
                    </span>
                </div>
                <div class="flex gap-2">
                    @if ($event->pades_applied)
                        <span class="rounded bg-violet-100 px-2 py-0.5 text-[11px] font-semibold text-violet-700">PAdES · sellado criptografico</span>
                    @endif
                    @if ($event->verified_at)
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Identidad verificada</span>
                    @endif
                </div>
            </div>

            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-400">Firmante</dt>
                    <dd class="text-slate-800">{{ $event->signer_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-400">Email verificado</dt>
                    <dd class="text-slate-800">{{ $event->signer_email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-400">Fecha de verificacion</dt>
                    <dd class="text-slate-800">{{ $event->verified_at?->format('d/m/Y H:i:s') ?? '—' }} UTC</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-slate-400">Direccion IP</dt>
                    <dd class="text-slate-800">{{ $event->ip_address ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase text-slate-400">Navegador</dt>
                    <dd class="truncate text-slate-600" title="{{ $event->user_agent }}">{{ $event->user_agent ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase text-slate-400">Hash SHA-256 documento (original)</dt>
                    <dd class="break-all font-mono text-xs text-slate-600">{{ $event->original_sha256 ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase text-slate-400">Hash SHA-256 documento (firmado)</dt>
                    <dd class="break-all font-mono text-xs text-slate-600">{{ $event->signed_sha256 ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    @empty
        <div class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-400">
            Este documento aun no tiene eventos de firma.
        </div>
    @endforelse
@endsection
