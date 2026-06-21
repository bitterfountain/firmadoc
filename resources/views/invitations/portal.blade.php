@extends('layouts.app')

@section('title', $document->original_name . ' · FirmaDoc')

@section('content')
    <div class="card mx-auto mt-8 max-w-2xl p-8">
        @if ($alreadySigned ?? false)
            <div class="brand-mark mx-auto mb-4 size-12">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <h1 class="text-center text-xl text-ink">{{ __('Ya has firmado') }}</h1>
            <p class="mt-2 text-center text-sm text-muted">
                {{ __('Gracias, :name. Tu firma ya consta en este documento.', ['name' => $invitation->name]) }}
            </p>
            @if ($invitation->signed_at)
                <p class="mt-1 text-center text-xs text-faint">
                    {{ __('Firmado el :date', ['date' => $invitation->signed_at->format('d/m/Y H:i')]) }} UTC
                </p>
            @endif
        @elseif ($notYourTurn ?? false)
            <div class="brand-mark mx-auto mb-4 size-12">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <h1 class="text-center text-xl text-ink">{{ __('Aún no es tu turno') }}</h1>
            <p class="mt-2 text-center text-sm text-muted">
                {{ __('Este documento se firma por orden. Te avisaremos cuando te toque.') }}
            </p>
        @else
            <h1 class="text-center text-xl text-ink">{{ __('Invitación caducada') }}</h1>
        @endif

        {{-- Estado general del documento --}}
        <div class="mt-8 border-t border-line pt-6">
            <h2 class="text-sm font-semibold text-ink">{{ __('Estado del documento') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $document->original_name }}</p>

            @php
                $total = $allInvitations->count();
                $signed = $allInvitations->where('status', 'signed')->count();
                $declined = $allInvitations->where('status', 'declined')->count();
                $pending = $allInvitations->where('status', 'pending')->count();
            @endphp

            <div class="mt-4 space-y-2">
                @foreach ($allInvitations as $inv)
                    <div class="flex items-center justify-between rounded-lg border border-line bg-paper/50 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">
                                <span class="text-faint">{{ $inv->position }}.</span>
                                {{ $inv->name }}
                                @if ($inv->id === $invitation->id)
                                    <span class="text-xs text-accent">({{ __('tú') }})</span>
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0">
                            @if ($inv->status === 'signed')
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-accent);background:var(--color-accent-soft)">
                                    {{ __('Firmado') }}
                                </span>
                            @elseif ($inv->status === 'declined')
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-danger);background:var(--color-danger-soft)">
                                    {{ __('Declinado') }}
                                </span>
                            @elseif ($inv->isMyTurn())
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-gold);background:rgba(176,135,56,0.12)">
                                    {{ __('Pendiente') }}
                                </span>
                            @elseif ($inv->isExpired())
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">
                                    {{ __('Caducado') }}
                                </span>
                            @else
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">
                                    {{ __('En espera') }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap gap-3 text-xs text-faint">
                <span>{{ __(':signed firmado(s)', ['signed' => $signed]) }}</span>
                @if ($pending > 0)
                    <span>{{ __(':count pendiente(s)', ['count' => $pending]) }}</span>
                @endif
            </div>

            {{-- Acciones para el firmante --}}
            @if (($alreadySigned ?? false) && $document->status === 'completed')
                <div class="mt-6">
                    <a href="{{ $document->user_id ? route('documents.download', $document) : route('quick.multi.download', $document->id) }}"
                       class="btn btn-primary w-full">
                        {{ __('Descargar PDF firmado') }}
                    </a>
                </div>
            @endif

            @if (($notYourTurn ?? false) && $invitation->status === 'pending' && ! $invitation->isExpired())
                <form action="{{ route('sign.decline', $invitation->token) }}" method="POST" class="mt-6"
                      onsubmit="return confirm('{{ __('¿Seguro que quieres declinar la firma?') }}')">
                    @csrf
                    <button type="submit" class="btn btn-danger w-full">{{ __('Declinar firma') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
