@extends('layouts.app')

@section('title', 'Mis documentos · FirmaDoc')

@section('content')
    <div class="grid gap-6 md:grid-cols-[1fr_1.4fr]">
        {{-- Subida --}}
        <section class="card h-fit p-6">
            <p class="eyebrow">Nuevo</p>
            <h1 class="mt-2 text-xl text-ink">Subir documento</h1>
            <p class="mt-1 text-sm leading-relaxed text-muted">
                PDF, Word (DOCX) o imagen (JPG/PNG). Todo se convierte a PDF para firmar.
            </p>

            <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="mt-5">
                @csrf
                <label for="file-input" class="group flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-line-strong bg-paper/40 px-4 py-8 text-center transition-colors hover:border-accent hover:bg-accent-soft/40">
                    <span class="grid size-11 place-items-center rounded-xl bg-accent-soft text-accent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/></svg>
                    </span>
                    <span class="mt-1 text-sm font-semibold text-ink">Pulsa para elegir un archivo</span>
                    <span id="file-name" class="text-xs text-faint">Ningún archivo seleccionado</span>
                </label>
                <input id="file-input" type="file" name="file" class="sr-only"
                       accept=".pdf,.docx,.doc,.odt,.jpg,.jpeg,.png"
                       onchange="document.getElementById('file-name').textContent = this.files[0]?.name || 'Ningún archivo seleccionado'">

                @error('file')
                    <p class="mt-2 text-sm" style="color:var(--color-danger)">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn btn-primary mt-4 w-full">Subir y convertir</button>
            </form>
        </section>

        {{-- Listado --}}
        <section>
            <div class="flex items-baseline justify-between">
                <h2 class="text-xl text-ink">Documentos</h2>
                <span class="text-xs text-faint">{{ $documents->count() }} en total</span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($documents as $document)
                    @php
                        $badge = match ($document->status) {
                            'signed', 'completed' => ['Firmado', 'var(--color-accent)', 'var(--color-accent-soft)'],
                            'ready' => ['Listo para firmar', 'var(--color-ink)', 'rgba(28,25,19,0.06)'],
                            'in_progress' => ['En progreso', 'var(--color-gold)', 'rgba(176,135,56,0.12)'],
                            'failed' => ['Error', 'var(--color-danger)', 'var(--color-danger-soft)'],
                            default => ['Procesando', 'var(--color-muted)', 'rgba(28,25,19,0.05)'],
                        };
                    @endphp
                    <div class="card flex items-center justify-between gap-3 p-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg" style="background:rgba(28,25,19,0.05);color:var(--color-muted)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ $document->original_name }}</p>
                                <p class="mt-1 flex items-center gap-2 text-xs text-faint">
                                    <span>{{ strtoupper($document->source_format) }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="color:{{ $badge[1] }};background:{{ $badge[2] }}">{{ $badge[0] }}</span>
                                </p>
                                @if ($document->status === 'failed' && $document->error)
                                    <p class="mt-1 truncate text-xs" style="color:var(--color-danger)" title="{{ $document->error }}">{{ $document->error }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($document->isReadyToSign() || in_array($document->status, ['signed', 'in_progress', 'completed']))
                                <a href="{{ route('documents.sign', $document) }}" class="btn btn-primary px-3 py-1.5 text-xs">
                                    {{ $document->status === 'completed' ? 'Ver' : 'Firmar' }}
                                </a>
                                <a href="{{ route('documents.signers', $document) }}" class="btn btn-ghost px-3 py-1.5 text-xs">Firmantes</a>
                            @endif
                            @if ($document->signed_path)
                                <a href="{{ route('documents.download', $document) }}" class="btn btn-ghost px-3 py-1.5 text-xs">Descargar</a>
                            @endif
                            @if ($document->signature_events_count > 0)
                                <a href="{{ route('documents.audit', $document) }}" class="btn btn-ghost px-3 py-1.5 text-xs">Auditoría</a>
                            @endif
                            <form action="{{ route('documents.destroy', $document) }}" method="POST" onsubmit="return confirm('¿Eliminar este documento?')">
                                @csrf
                                @method('DELETE')
                                <button class="grid size-8 place-items-center rounded-lg text-faint transition-colors hover:bg-[var(--color-danger-soft)] hover:text-[var(--color-danger)]" title="Eliminar">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="card p-10 text-center">
                        <p class="text-sm text-muted">Aún no has subido ningún documento.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    @if (auth()->user()->is_admin)
        <section class="mt-10">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <div>
                    <h2 class="text-xl text-ink">Invitaciones Pro</h2>
                    <p class="mt-0.5 text-sm text-muted">Enlaces de un solo uso que dan 1 año de cuenta profesional gratis.</p>
                </div>
                <form method="POST" action="{{ route('invites.store') }}">
                    @csrf
                    <button class="btn btn-primary px-4 py-2 text-sm">Generar enlace</button>
                </form>
            </div>

            @if (session('invite_url'))
                <div class="card mt-4 p-4" style="border-color:var(--color-accent)">
                    <p class="text-sm font-semibold text-accent">Enlace creado — cópialo y compártelo (un solo uso):</p>
                    <input readonly onclick="this.select()" value="{{ session('invite_url') }}"
                           class="input mt-2 font-mono text-xs">
                </div>
            @endif

            <div class="mt-4 space-y-2">
                @forelse ($invites as $inv)
                    @php
                        [$txt, $col, $bg] = $inv->used_at
                            ? ['Usado', 'var(--color-muted)', 'rgba(28,25,19,0.05)']
                            : (! $inv->isUsable()
                                ? ['Caducado', 'var(--color-danger)', 'var(--color-danger-soft)']
                                : ['Activo', 'var(--color-accent)', 'var(--color-accent-soft)']);
                    @endphp
                    <div class="card flex items-center justify-between gap-3 p-3">
                        <input readonly onclick="this.select()" value="{{ $inv->url() }}"
                               class="min-w-0 flex-1 truncate bg-transparent font-mono text-xs text-muted outline-none">
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="color:{{ $col }};background:{{ $bg }}">{{ $txt }}</span>
                    </div>
                @empty
                    <p class="text-sm text-faint">Aún no has generado invitaciones.</p>
                @endforelse
            </div>
        </section>
    @endif
@endsection
