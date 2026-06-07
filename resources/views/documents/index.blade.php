@extends('layouts.app')

@section('title', 'Mis documentos · FirmaDoc')

@section('content')
    <div class="grid gap-6 md:grid-cols-[1fr_1.4fr]">
        {{-- Subida --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h1 class="text-lg font-semibold text-slate-900">Subir documento</h1>
            <p class="mt-1 text-sm text-slate-500">
                PDF, Word (DOCX) o imagen (JPG/PNG). Todo se convierte a PDF para firmar.
            </p>

            <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="mt-4">
                @csrf
                <label for="file-input" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition hover:border-indigo-400 hover:bg-indigo-50/40">
                    <span class="text-3xl">📄</span>
                    <span class="text-sm font-medium text-slate-700">Pulsa para elegir un archivo</span>
                    <span id="file-name" class="text-xs text-slate-400">Ningun archivo seleccionado</span>
                </label>
                <input id="file-input" type="file" name="file" class="sr-only"
                       accept=".pdf,.docx,.doc,.odt,.jpg,.jpeg,.png"
                       onchange="document.getElementById('file-name').textContent = this.files[0]?.name || 'Ningun archivo seleccionado'">

                @error('file')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    Subir y convertir
                </button>
            </form>
        </section>

        {{-- Listado --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">Documentos</h2>

            @forelse ($documents as $document)
                <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ $document->original_name }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ strtoupper($document->source_format) }} ·
                            @php
                                $badge = match ($document->status) {
                                    'signed' => ['Firmado', 'bg-emerald-100 text-emerald-700'],
                                    'ready' => ['Listo para firmar', 'bg-indigo-100 text-indigo-700'],
                                    'failed' => ['Error', 'bg-rose-100 text-rose-700'],
                                    default => ['Procesando', 'bg-slate-100 text-slate-600'],
                                };
                            @endphp
                            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                        </p>
                        @if ($document->status === 'failed' && $document->error)
                            <p class="mt-1 truncate text-xs text-rose-500" title="{{ $document->error }}">{{ $document->error }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($document->isReadyToSign() || in_array($document->status, ['signed', 'in_progress', 'completed']))
                            <a href="{{ route('documents.sign', $document) }}"
                               class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                {{ $document->status === 'completed' ? 'Ver' : 'Firmar' }}
                            </a>
                            <a href="{{ route('documents.signers', $document) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Firmantes
                            </a>
                        @endif
                        @if ($document->signed_path)
                            <a href="{{ route('documents.download', $document) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Descargar
                            </a>
                        @endif
                        @if ($document->signature_events_count > 0)
                            <a href="{{ route('documents.audit', $document) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Auditoria
                            </a>
                        @endif
                        <form action="{{ route('documents.destroy', $document) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar este documento?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Eliminar">✕</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="mt-4 text-sm text-slate-400">Aun no has subido ningun documento.</p>
            @endforelse
        </section>
    </div>
@endsection
