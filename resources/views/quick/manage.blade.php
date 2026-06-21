@extends('layouts.app')

@section('title', __('Documento multi-firmante') . ' · FirmaDoc')

@section('content')
    <div class="mb-5">
        <a href="{{ route('quick.start') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            {{ __('Inicio') }}
        </a>
        <h1 class="mt-1 text-xl text-ink">{{ $document->original_name }}</h1>
        <p class="text-sm text-muted">{{ __('Documento multi-firmante · sin cuenta') }}</p>
    </div>

    @php
        $total = $invitations->count();
        $signed = $invitations->where('status', 'signed')->count();
        $pending = $invitations->where('status', 'pending')->count();
    @endphp

    <div class="grid gap-6 md:grid-cols-[1.4fr_1fr]">
        <section>
            <div class="flex items-baseline justify-between">
                <h2 class="text-lg text-ink">{{ __('Firmantes') }}</h2>
                <span class="text-xs text-faint">{{ __(':signed de :total firmado(s)', ['signed' => $signed, 'total' => $total]) }}</span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($invitations as $invitation)
                    <div class="card flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">
                                <span class="text-faint">{{ $invitation->position }}.</span> {{ $invitation->name }}
                            </p>
                            <p class="truncate text-xs text-faint">{{ $invitation->email }}</p>
                            @if ($invitation->status !== 'signed')
                                <input type="text" readonly value="{{ route('sign.show', $invitation->token) }}"
                                       onclick="this.select()"
                                       class="mt-2 w-full max-w-md rounded-lg border border-line bg-paper/50 px-2.5 py-1.5 text-[11px] text-muted">
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($invitation->status === 'signed')
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-accent);background:var(--color-accent-soft)">
                                    {{ __('Firmado') }} {{ $invitation->signed_at?->format('d/m H:i') }}
                                </span>
                            @elseif ($invitation->status === 'declined')
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-danger);background:var(--color-danger-soft)">
                                    {{ __('Declinado') }}
                                </span>
                            @elseif ($invitation->isExpired())
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">
                                    {{ __('Caducado') }}
                                </span>
                            @elseif ($invitation->isMyTurn())
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-gold);background:rgba(176,135,56,0.12)">{{ __('Su turno') }}</span>
                            @else
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">{{ __('En espera') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="card p-8 text-center text-sm text-muted">{{ __('Sin firmantes.') }}</div>
                @endforelse
            </div>
        </section>

        <section class="card h-fit p-6">
            <h2 class="text-lg text-ink">{{ __('Información') }}</h2>
            <p class="mt-1 text-xs leading-relaxed text-muted">
                {{ __('Este documento multi-firmante se creó sin cuenta. Los enlaces de firma son privados.') }}
            </p>

            <div class="mt-4 space-y-2 text-sm">
                <p><span class="text-faint">{{ __('Modo:') }}</span>
                    <span class="text-ink">{{ $document->isSequential() ? __('Secuencial') : __('Paralelo') }}</span>
                </p>
                <p><span class="text-faint">{{ __('Firmado:') }}</span>
                    <span class="text-ink">{{ $signed }}/{{ $total }}</span>
                </p>
                @if ($document->status === 'completed' && $document->signed_path)
                    <a href="{{ route('quick.multi.download', $document->id) }}" class="btn btn-primary mt-3 w-full">
                        {{ __('Descargar PDF firmado') }}
                    </a>
                @endif
            </div>

            <p class="mt-4 text-[11px] text-faint">
                {{ __('Guarda este enlace para volver. El documento se eliminará automáticamente pasados 7 días tras la última firma.') }}
            </p>
        </section>
    </div>
@endsection
