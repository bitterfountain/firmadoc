@extends('layouts.app')

@section('title', 'Firmantes · FirmaDoc')

@section('content')
    <div class="mb-4">
        <a href="{{ route('documents.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Volver</a>
        <h1 class="text-lg font-semibold text-slate-900">Firmantes</h1>
        <p class="text-sm text-slate-500">{{ $document->original_name }}</p>
    </div>

    @php
        $total = $invitations->count();
        $signed = $invitations->where('status', 'signed')->count();
    @endphp

    <div class="grid gap-6 md:grid-cols-[1.4fr_1fr]">
        {{-- Lista de firmantes --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Orden de firma</h2>
                @if ($total > 0)
                    <span class="text-xs text-slate-500">{{ $signed }} / {{ $total }} firmado(s)</span>
                @endif
            </div>

            @forelse ($invitations as $invitation)
                <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">
                            <span class="text-slate-400">{{ $invitation->position }}.</span> {{ $invitation->name }}
                        </p>
                        <p class="truncate text-xs text-slate-400">{{ $invitation->email }}</p>
                        @if ($invitation->status !== 'signed')
                            <input type="text" readonly value="{{ route('sign.show', $invitation->token) }}"
                                   onclick="this.select()"
                                   class="mt-1 w-full max-w-md rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] text-slate-500">
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($invitation->status === 'signed')
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                Firmado {{ $invitation->signed_at?->format('d/m H:i') }}
                            </span>
                        @elseif ($invitation->isMyTurn())
                            <span class="rounded bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">Su turno</span>
                        @else
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">En espera</span>
                        @endif

                        @if ($invitation->status !== 'signed')
                            <form action="{{ route('documents.invitations.destroy', [$document, $invitation]) }}" method="POST"
                                  onsubmit="return confirm('¿Quitar este firmante?')">
                                @csrf @method('DELETE')
                                <button class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Quitar">✕</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="mt-4 text-sm text-slate-400">Aun no has anadido firmantes.</p>
            @endforelse
        </section>

        {{-- Anadir firmante --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Anadir firmante</h2>
            <p class="mt-1 text-xs text-slate-500">Se le enviara un enlace privado por email. Firman por orden de adicion.</p>

            <form action="{{ route('documents.invitations.store', $document) }}" method="POST" class="mt-4 space-y-3">
                @csrf
                <input type="text" name="name" placeholder="Nombre completo" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                @error('name') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                <input type="email" name="email" placeholder="email@ejemplo.com" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                @error('email') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Enviar invitacion
                </button>
            </form>
        </section>
    </div>
@endsection
