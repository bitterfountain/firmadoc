@extends('layouts.app')

@section('title', __('Firmantes') . ' · FirmaDoc')

@section('content')
    <div class="mb-5">
        <a href="{{ route('documents.index') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            {{ __('Volver') }}
        </a>
        <h1 class="mt-1 text-xl text-ink">{{ __('Firmantes') }}</h1>
        <p class="text-sm text-muted">{{ $document->original_name }}</p>
    </div>

    @php
        $total = $invitations->count();
        $signed = $invitations->where('status', 'signed')->count();
        $declined = $invitations->where('status', 'declined')->count();
    @endphp

    <div class="grid gap-6 md:grid-cols-[1.4fr_1fr]">
        <section>
            <div class="flex items-baseline justify-between">
                <h2 class="text-lg text-ink">{{ __('Orden de firma') }}</h2>
                @if ($total > 0)
                    <span class="text-xs text-faint">
                        {{ __(':signed de :total firmado(s)', ['signed' => $signed, 'total' => $total]) }}
                        @if ($declined > 0)
                            · {{ __(':count declinado(s)', ['count' => $declined]) }}
                        @endif
                    </span>
                @endif
            </div>

            {{-- Selector modo de firma --}}
            <div class="mt-3 flex items-center gap-3">
                <form action="{{ route('documents.signing-mode', $document) }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <select name="signing_mode" onchange="this.form.submit()"
                            class="rounded-lg border border-line bg-paper/50 px-2.5 py-1.5 text-xs text-ink">
                        <option value="sequential" {{ $document->signing_mode !== 'parallel' ? 'selected' : '' }}>
                            {{ __('Firma secuencial (por orden)') }}
                        </option>
                        <option value="parallel" {{ $document->signing_mode === 'parallel' ? 'selected' : '' }}>
                            {{ __('Firma paralela (cualquier orden)') }}
                        </option>
                    </select>
                </form>
                <span class="text-[11px] text-faint">
                    @if ($document->isSequential())
                        {{ __('Cada firmante espera a que el anterior complete.') }}
                    @else
                        {{ __('Todos pueden firmar en cualquier orden.') }}
                    @endif
                </span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($invitations as $invitation)
                    <div class="card flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">
                                <span class="text-faint">{{ $invitation->position }}.</span> {{ $invitation->name }}
                            </p>
                            <p class="truncate text-xs text-faint">{{ $invitation->email }}</p>
                            @if ($invitation->expires_at)
                                <p class="mt-0.5 text-[10px] {{ $invitation->isExpired() ? 'text-[var(--color-danger)]' : 'text-faint' }}">
                                    {{ $invitation->isExpired() ? __('Caducó') : __('Caduca') }}
                                    {{ $invitation->expires_at->format('d/m/Y') }}
                                </p>
                            @endif
                            @if ($invitation->status === 'pending' && ! $invitation->isExpired())
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
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">{{ __('Caducado') }}</span>
                            @elseif ($invitation->isMyTurn())
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-gold);background:rgba(176,135,56,0.12)">{{ __('Su turno') }}</span>
                            @else
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">{{ __('En espera') }}</span>
                            @endif

                            @if ($invitation->status !== 'signed')
                                <form action="{{ route('documents.invitations.destroy', [$document, $invitation]) }}" method="POST" onsubmit="return confirm('{{ __('¿Quitar este firmante?') }}')">
                                    @csrf @method('DELETE')
                                    <button class="grid size-7 place-items-center rounded-lg text-faint transition-colors hover:bg-[var(--color-danger-soft)] hover:text-[var(--color-danger)]" title="{{ __('Quitar') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="card p-8 text-center text-sm text-muted">{{ __('Aún no has añadido firmantes.') }}</div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-5">
            {{-- Anadir firmante --}}
            <section class="card h-fit p-6">
                <h2 class="text-lg text-ink">{{ __('Añadir firmante') }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    {{ __('Se le enviará un enlace privado por email.') }}
                    @if ($document->isSequential())
                        {{ __('Firman por orden de adición.') }}
                    @else
                        {{ __('Pueden firmar en cualquier orden.') }}
                    @endif
                </p>

                <form action="{{ route('documents.invitations.store', $document) }}" method="POST" class="mt-4 space-y-3">
                    @csrf
                    <input type="text" name="name" placeholder="{{ __('Nombre completo') }}" required class="input">
                    @error('name') <p class="text-xs" style="color:var(--color-danger)">{{ $message }}</p> @enderror
                    <input type="email" name="email" placeholder="email@ejemplo.com" required class="input">
                    @error('email') <p class="text-xs" style="color:var(--color-danger)">{{ $message }}</p> @enderror
                    <input type="text" name="phone" placeholder="{{ __('Teléfono (SMS, opcional)') }}" class="input">
                    <div>
                        <label class="text-xs text-faint">{{ __('Caduca en (días)') }}</label>
                        <input type="number" name="expires_at" value="30" min="1" max="365" class="input mt-1">
                    </div>
                    <button class="btn btn-primary w-full">{{ __('Enviar invitación') }}</button>
                </form>
            </section>

            {{-- Testigo --}}
            <section class="card h-fit p-6">
                <h2 class="text-lg text-ink">{{ __('Testigo') }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    {{ __('Designa un testigo que confirme haber presenciado las firmas.') }}
                </p>

                @if ($document->witness_name)
                    <div class="mt-3 rounded-lg border border-line bg-paper/50 p-3">
                        <p class="text-sm font-medium text-ink">{{ $document->witness_name }}</p>
                        <p class="text-xs text-faint">{{ $document->witness_email }}</p>
                        @if ($document->witness_confirmed_at)
                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" style="color:var(--color-accent);background:var(--color-accent-soft)">
                                {{ __('Confirmado') }} {{ $document->witness_confirmed_at->format('d/m/Y H:i') }}
                            </span>
                        @else
                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" style="color:var(--color-muted);background:rgba(28,25,19,0.05)">
                                {{ __('Pendiente de confirmar') }}
                            </span>
                        @endif
                    </div>
                @else
                    <form action="{{ route('documents.witness', $document) }}" method="POST" class="mt-4 space-y-3">
                        @csrf
                        <input type="text" name="witness_name" placeholder="{{ __('Nombre del testigo') }}" required class="input">
                        <input type="email" name="witness_email" placeholder="email@ejemplo.com" required class="input">
                        <button class="btn btn-ghost w-full">{{ __('Designar testigo') }}</button>
                    </form>
                @endif
            </section>

            {{-- Webhook --}}
            <section class="card h-fit p-6">
                <h2 class="text-lg text-ink">{{ __('Webhook') }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    {{ __('URL para recibir notificaciones de eventos de firma (POST JSON).') }}
                </p>
                <form action="{{ route('documents.webhook', $document) }}" method="POST" class="mt-4 space-y-3">
                    @csrf
                    <input type="url" name="webhook_url" value="{{ $document->webhook_url }}"
                           placeholder="https://ejemplo.com/webhook" class="input">
                    <button class="btn btn-ghost w-full">{{ __('Guardar') }}</button>
                </form>
            </section>
        </aside>
    </div>
@endsection
