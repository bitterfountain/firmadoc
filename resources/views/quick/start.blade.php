@extends('layouts.app')

@section('title', __('Firma rápida') . ' · FirmaDoc')

@section('content')
    <div class="mx-auto mt-6 max-w-lg">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            {{ __('Inicio') }}
        </a>

        {{-- Tabs: simple / multi --}}
        <div class="card mt-3 p-1" id="quick-tabs">
            <div class="flex rounded-xl bg-paper/50 p-1" role="tablist">
                <button role="tab" data-mode="simple" class="tab-btn flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors active">
                    {{ __('Firma única') }}
                </button>
                <button role="tab" data-mode="multi" class="tab-btn flex-1 rounded-lg px-4 py-2 text-sm font-semibold text-muted transition-colors">
                    {{ __('Multi-firmante') }}
                </button>
            </div>
        </div>

        <div class="card mt-3 p-8" id="tab-simple">
            <p class="eyebrow">{{ __('Sin cuenta') }}</p>
            <h1 class="mt-2 text-2xl text-ink">{{ __('Firma rápida') }}</h1>
            <p class="mt-1.5 text-sm leading-relaxed text-muted">
                {!! __('Sube tu documento (PDF, Word o imagen) y fírmalo en el navegador. Opcional: deja tu email para recibir una copia. <strong class="text-faint">No guardamos nada en el servidor.</strong>') !!}
            </p>

            <form method="POST" action="{{ route('quick.upload') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="mode" value="simple">
                <label for="quick-file" class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-line-strong bg-paper/40 px-4 py-10 text-center transition-colors hover:border-accent hover:bg-accent-soft/40">
                    <span class="grid size-12 place-items-center rounded-xl bg-accent-soft text-accent transition-transform group-hover:-translate-y-0.5">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/></svg>
                    </span>
                    <span class="mt-3 text-sm font-semibold text-ink">{{ __('Pulsa para elegir un archivo') }}</span>
                    <span class="mt-1 text-xs text-faint">PDF · DOCX · DOC · ODT · JPG · PNG</span>
                    <span data-role="quick-file-name" class="mt-2 rounded-full bg-ink px-4 py-1.5 text-xs font-semibold text-paper">{{ __('Seleccionar archivo') }}</span>
                    <input id="quick-file" type="file" name="file" required class="sr-only"
                           accept=".pdf,.docx,.doc,.odt,.jpg,.jpeg,.png"
                           onchange="this.closest('label').querySelector('[data-role=quick-file-name]').textContent = this.files[0]?.name || '{{ __('Seleccionar archivo') }}'">
                </label>

                @error('file')
                    <p class="text-sm" style="color:var(--color-danger)">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn btn-primary w-full">{{ __('Subir y firmar') }}</button>
            </form>
        </div>

        <div class="card mt-3 hidden p-8" id="tab-multi">
            <p class="eyebrow">{{ __('Sin cuenta') }}</p>
            <h1 class="mt-2 text-2xl text-ink">{{ __('Firma multi-firmante') }}</h1>
            <p class="mt-1.5 text-sm leading-relaxed text-muted">
                {{ __('Sube un documento e invita a varias personas a firmarlo. Cada una recibe un enlace privado. Sin registro, sin verificación de identidad. El documento se elimina automáticamente tras 7 días.') }}
            </p>

            <form method="POST" action="{{ route('quick.upload') }}" enctype="multipart/form-data" class="mt-6 space-y-4" id="multi-form">
                @csrf
                <input type="hidden" name="mode" value="multi">

                <label for="quick-multi-file" class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-line-strong bg-paper/40 px-4 py-8 text-center transition-colors hover:border-accent hover:bg-accent-soft/40">
                    <span class="grid size-12 place-items-center rounded-xl bg-accent-soft text-accent transition-transform group-hover:-translate-y-0.5">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/></svg>
                    </span>
                    <span class="mt-3 text-sm font-semibold text-ink">{{ __('Pulsa para elegir un archivo') }}</span>
                    <span class="mt-1 text-xs text-faint">PDF · DOCX · DOC · ODT · JPG · PNG</span>
                    <span data-role="quick-multi-file-name" class="mt-2 rounded-full bg-ink px-4 py-1.5 text-xs font-semibold text-paper">{{ __('Seleccionar archivo') }}</span>
                    <input id="quick-multi-file" type="file" name="file" required class="sr-only"
                           accept=".pdf,.docx,.doc,.odt,.jpg,.jpeg,.png"
                           onchange="this.closest('label').querySelector('[data-role=quick-multi-file-name]').textContent = this.files[0]?.name || '{{ __('Seleccionar archivo') }}'">
                </label>

                <div class="rounded-xl border border-line bg-paper/30 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-ink">{{ __('Firmantes') }}</p>
                        <button type="button" id="add-signer" class="text-xs font-semibold text-accent hover:underline">
                            + {{ __('Añadir') }}
                        </button>
                    </div>

                    <div id="signers-list" class="mt-3 space-y-2">
                        <div class="signer-row flex gap-2">
                            <input type="text" name="signers[0][name]" placeholder="{{ __('Nombre') }}" required class="input flex-1">
                            <input type="email" name="signers[0][email]" placeholder="email@ejemplo.com" required class="input flex-[2]">
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <label class="flex items-center gap-2 text-xs text-muted">
                            <input type="radio" name="signing_mode" value="parallel" checked>
                            {{ __('Cualquier orden') }}
                        </label>
                        <label class="flex items-center gap-2 text-xs text-muted">
                            <input type="radio" name="signing_mode" value="sequential">
                            {{ __('Por orden') }}
                        </label>
                    </div>
                </div>

                @error('file')
                    <p class="text-sm" style="color:var(--color-danger)">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn btn-primary w-full">{{ __('Subir y enviar invitaciones') }}</button>
            </form>
        </div>

        <p class="mt-5 text-center text-xs text-faint">
            {!! __('¿Necesitas firma criptográfica (PAdES) o varios firmantes? <a href=":url" class="font-semibold text-accent hover:underline">Entra con tu cuenta</a>.', ['url' => route('login')]) !!}
        </p>
    </div>

    @push('scripts')
    <script>
        (function() {
            let signerIdx = 1;
            const tabBtns = document.querySelectorAll('#quick-tabs .tab-btn');
            const tabSimple = document.getElementById('tab-simple');
            const tabMulti = document.getElementById('tab-multi');
            const multiFile = document.getElementById('quick-multi-file');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabBtns.forEach(b => b.classList.remove('active', 'text-ink'));
                    tabBtns.forEach(b => b.classList.add('text-muted'));
                    btn.classList.add('active', 'text-ink');
                    btn.classList.remove('text-muted');

                    const mode = btn.dataset.mode;
                    tabSimple.classList.toggle('hidden', mode !== 'simple');
                    tabMulti.classList.toggle('hidden', mode !== 'multi');

                    if (mode === 'multi') {
                        multiFile.setAttribute('required', 'required');
                    } else {
                        multiFile.removeAttribute('required');
                    }
                });
            });

            document.getElementById('add-signer')?.addEventListener('click', () => {
                const row = document.createElement('div');
                row.className = 'signer-row flex gap-2';
                row.innerHTML = `
                    <input type="text" name="signers[${signerIdx}][name]" placeholder="{{ __('Nombre') }}" required class="input flex-1">
                    <input type="email" name="signers[${signerIdx}][email]" placeholder="email@ejemplo.com" required class="input flex-[2]">
                    <button type="button" class="remove-signer grid size-9 shrink-0 place-items-center rounded-lg text-faint hover:bg-[var(--color-danger-soft)] hover:text-[var(--color-danger)]" title="{{ __('Quitar') }}">&times;</button>
                `;
                row.querySelector('.remove-signer').addEventListener('click', () => row.remove());
                document.getElementById('signers-list').appendChild(row);
                signerIdx++;
            });
        })();
    </script>
    @endpush
@endsection
