@extends('layouts.app')

@section('title', 'Firmar · FirmaDoc')

@section('content')
    @php
        // URLs configurables: sirven tanto para firma por documento como por invitacion (token).
        $pdfUrl ??= route('documents.pdf', $document);
        $saveUrl ??= route('documents.storeSigned', $document);
        $otpUrl ??= route('documents.otp', $document);
        $otpVerifyUrl ??= route('documents.otpVerify', $document);
        $backUrl ??= route('documents.index');
        $headerTitle ??= $document->original_name;
        $signerName ??= null;
        $signerEmail ??= null;
    @endphp
    <div id="signer"
         data-pdf-url="{{ $pdfUrl }}"
         data-save-url="{{ $saveUrl }}"
         data-otp-url="{{ $otpUrl }}"
         data-otp-verify-url="{{ $otpVerifyUrl }}"
         data-index-url="{{ $backUrl }}"
         class="select-none">

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
                    Volver
                </a>
                <h1 class="mt-1 truncate text-xl text-ink">{{ $headerTitle }}</h1>
            </div>

            {{-- Navegacion de paginas --}}
            <div class="flex items-center gap-1 rounded-full border border-line bg-surface px-1.5 py-1">
                <button data-action="prev" class="grid size-7 place-items-center rounded-full text-muted hover:bg-[rgba(28,25,19,0.05)]" type="button">‹</button>
                <span class="px-1 text-xs text-muted">Pág. <span data-role="page">1</span> / <span data-role="pages">?</span></span>
                <button data-action="next" class="grid size-7 place-items-center rounded-full text-muted hover:bg-[rgba(28,25,19,0.05)]" type="button">›</button>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1fr_300px]">
            {{-- Visor del PDF con capa de marcado encima --}}
            <div class="overflow-auto rounded-2xl border border-line bg-[#ece7da] p-4">
                <div data-role="stage" class="relative mx-auto w-fit shadow-[0_18px_40px_-20px_rgba(28,25,19,0.45)]">
                    <canvas data-role="pdf-canvas" class="block bg-white"></canvas>
                    <div data-role="overlay" class="absolute inset-0 cursor-crosshair touch-none"></div>
                </div>
            </div>

            {{-- Panel lateral --}}
            <aside class="space-y-4">
                <div class="card p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-ink">
                        <span class="grid size-5 place-items-center rounded-full bg-accent-soft text-[11px] font-bold text-accent" style="font-family:var(--font-sans)">1</span>
                        Tu firma
                    </h2>
                    <p class="mt-1.5 text-xs leading-relaxed text-muted">Dibuja con el ratón o el dedo. Luego arrástrala sobre el documento para colocarla.</p>
                    <div class="mt-3 rounded-xl border border-line-strong bg-paper/50">
                        <canvas data-role="sigpad" class="block h-32 w-full touch-none rounded-xl"></canvas>
                    </div>
                    <button data-action="clear-sig" type="button" class="btn btn-ghost mt-3 w-full px-3 py-1.5 text-xs">Limpiar</button>
                </div>

                <div class="card p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-ink">
                        <span class="grid size-5 place-items-center rounded-full bg-accent-soft text-[11px] font-bold text-accent" style="font-family:var(--font-sans)">2</span>
                        Firmas en el documento
                    </h2>
                    <p class="mt-1.5 text-xs leading-relaxed text-muted">
                        Arrastra sobre el documento para colocar la firma. Puedes añadir <strong class="text-ink">varias</strong>.
                        Mueve cada caja arrastrándola, redimensiona por las esquinas o bórrala con la <span class="font-semibold" style="color:var(--color-danger)">×</span>.
                    </p>
                    <p data-role="zone-info" class="mt-2 text-xs text-faint">Sin firmas colocadas.</p>
                    <button data-action="clear-zones" type="button" class="btn btn-danger mt-3 w-full px-3 py-1.5 text-xs">Quitar todas las firmas</button>
                </div>

                <button data-action="apply" type="button" class="btn btn-primary w-full py-3 disabled:opacity-50">
                    <span class="grid size-5 place-items-center rounded-full bg-[rgba(245,242,234,0.2)] text-[11px] font-bold" style="font-family:var(--font-sans)">3</span>
                    Verificar identidad y firmar
                </button>
                <p data-role="apply-hint" class="text-center text-[11px] text-faint">Te enviaremos un código por email para confirmar la firma.</p>

                <div data-role="result" class="hidden rounded-2xl border border-accent/20 bg-accent-soft p-5 text-center">
                    <p class="text-sm font-semibold text-accent">¡Documento firmado!</p>
                    <a data-role="download" href="#" class="btn btn-primary mt-3 px-4 py-2 text-xs">Descargar PDF firmado</a>
                </div>

                <p data-role="status" class="text-center text-xs text-faint"></p>
            </aside>
        </div>

        {{-- Modal de verificacion de identidad (OTP por email) --}}
        <div data-role="verify-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(28,25,19,0.45)">
            <div class="card w-full max-w-sm p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg text-ink">Verifica tu identidad</h3>
                    <button data-action="modal-close" type="button" class="text-faint transition-colors hover:text-ink">✕</button>
                </div>

                {{-- Paso A: datos del firmante --}}
                <div data-role="step-data" class="mt-4 space-y-3">
                    <p data-role="data-hint" class="text-xs text-muted">Te enviaremos un código por email para confirmar la firma.</p>
                    <input data-role="signer-name" type="text" placeholder="Nombre completo" autocomplete="name"
                           value="{{ $signerName }}" @if($signerName) readonly @endif class="input read-only:bg-paper/60">
                    <input data-role="signer-email" type="email" placeholder="tu@email.com" autocomplete="email"
                           value="{{ $signerEmail }}" @if($signerEmail) readonly @endif class="input read-only:bg-paper/60">
                    <button data-action="send-code" type="button" class="btn btn-primary w-full">Enviar código</button>

                    {{-- Solo Nivel 0 (firma rápida): alternativa sin email --}}
                    <div data-role="quick-direct" class="hidden">
                        <div class="my-1 flex items-center gap-3 text-[11px] uppercase tracking-wider text-faint">
                            <span class="h-px flex-1" style="background:var(--color-line)"></span>
                            o
                            <span class="h-px flex-1" style="background:var(--color-line)"></span>
                        </div>
                        <button data-action="direct-download" type="button" class="btn btn-ghost mt-2 w-full">Descarga directa</button>
                    </div>
                </div>

                {{-- Paso B: codigo OTP --}}
                <div data-role="step-otp" class="mt-4 hidden space-y-3">
                    <p class="text-xs text-muted">
                        Introduce el código de 6 dígitos enviado a
                        <span data-role="sent-email" class="font-medium text-ink"></span>.
                    </p>
                    <input data-role="otp-input" inputmode="numeric" maxlength="6" placeholder="------"
                           class="input text-center text-lg tracking-[0.5em]">
                    <button data-action="verify-code" type="button" class="btn btn-primary w-full">Verificar y firmar</button>
                    <button data-action="resend-code" type="button" class="w-full text-xs text-faint transition-colors hover:text-ink">Cambiar datos / reenviar</button>
                </div>

                <p data-role="modal-status" class="mt-3 text-center text-xs text-faint"></p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/sign.js')
@endpush
