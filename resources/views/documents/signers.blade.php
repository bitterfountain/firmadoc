@extends('layouts.app')

@section('title', 'Firmantes · FirmaDoc')

@section('content')
    <div class="mb-5">
        <a href="{{ route('documents.index') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Volver
        </a>
        <h1 class="mt-1 text-xl text-ink">Firmantes</h1>
        <p class="text-sm text-muted">{{ $document->original_name }}</p>
    </div>

    @php
        $total = $invitations->count();
        $signed = $invitations->where('status', 'signed')->count();
    @endphp

    <div class="grid gap-6 md:grid-cols-[1.4fr_1fr]">
        {{-- Lista de firmantes --}}
        <section>
            <div class="flex items-baseline justify-between">
                <h2 class="text-lg text-ink">Orden de firma</h2>
                @if ($total > 0)
                    <span class="text-xs text-faint">{{ $signed }} / {{ $total }} firmado(s)</span>
                @endif
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
                                    Firmado {{ $invitation->signed_at?->format('d/m H:i') }}
                                </span>
                            @elseif ($invitation->isMyTurn())
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-gold);background:rgba(176,135,56,0.12)">Su turno</span>
                            @else
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">En espera</span>
                            @endif

                            @if ($invitation->status !== 'signed')
                                <form action="{{ route('documents.invitations.destroy', [$document, $invitation]) }}" method="POST" onsubmit="return confirm('¿Quitar este firmante?')">
                                    @csrf @method('DELETE')
                                    <button class="grid size-7 place-items-center rounded-lg text-faint transition-colors hover:bg-[var(--color-danger-soft)] hover:text-[var(--color-danger)]" title="Quitar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="card p-8 text-center text-sm text-muted">Aún no has añadido firmantes.</div>
                @endforelse
            </div>
        </section>

        {{-- Anadir firmante --}}
        <section class="card h-fit p-6">
            <h2 class="text-lg text-ink">Añadir firmante</h2>
            <p class="mt-1 text-xs leading-relaxed text-muted">Se le enviará un enlace privado por email. Firman por orden de adición.</p>

            <form action="{{ route('documents.invitations.store', $document) }}" method="POST" class="mt-4 space-y-3">
                @csrf
                <input type="text" name="name" placeholder="Nombre completo" required class="input">
                @error('name') <p class="text-xs" style="color:var(--color-danger)">{{ $message }}</p> @enderror
                <input type="email" name="email" placeholder="email@ejemplo.com" required class="input">
                @error('email') <p class="text-xs" style="color:var(--color-danger)">{{ $message }}</p> @enderror
                <button class="btn btn-primary w-full">Enviar invitación</button>
            </form>
        </section>
    </div>
@endsection
