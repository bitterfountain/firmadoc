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

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ $backUrl }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Volver</a>
                <h1 class="text-lg font-semibold text-slate-900">{{ $headerTitle }}</h1>
            </div>

            {{-- Navegacion de paginas --}}
            <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1">
                <button data-action="prev" class="rounded px-2 py-1 text-slate-600 hover:bg-slate-100" type="button">‹</button>
                <span class="text-sm text-slate-600">Pag. <span data-role="page">1</span> / <span data-role="pages">?</span></span>
                <button data-action="next" class="rounded px-2 py-1 text-slate-600 hover:bg-slate-100" type="button">›</button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_280px]">
            {{-- Visor del PDF con capa de marcado encima --}}
            <div class="overflow-auto rounded-xl border border-slate-200 bg-slate-200 p-4">
                <div data-role="stage" class="relative mx-auto w-fit shadow-lg">
                    <canvas data-role="pdf-canvas" class="block bg-white"></canvas>
                    {{-- Capa transparente para dibujar la zona de firma --}}
                    <div data-role="overlay" class="absolute inset-0 cursor-crosshair touch-none"></div>
                </div>
            </div>

            {{-- Panel lateral --}}
            <aside class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-slate-900">1 · Tu firma</h2>
                    <p class="mt-1 text-xs text-slate-500">Dibuja con el raton o el dedo. Luego arrastrala sobre el documento para colocarla.</p>
                    <div class="mt-2 rounded-lg border border-slate-300 bg-slate-50">
                        <canvas data-role="sigpad" class="block h-32 w-full touch-none rounded-lg"></canvas>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <button data-action="clear-sig" type="button"
                                class="flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Limpiar
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-slate-900">2 · Firmas en el documento</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Arrastra sobre el documento para colocar la firma. Puedes añadir <strong>varias</strong>
                        (incluso de distintas personas: cambia la firma del pad y coloca otra). Mueve cada caja
                        arrastrandola, redimensiona por las esquinas o borra con la <span class="font-semibold text-rose-500">×</span>.
                    </p>
                    <p data-role="zone-info" class="mt-2 text-xs text-slate-400">Sin firmas colocadas.</p>
                    <p class="mt-2 text-xs text-slate-400">
                        Para borrar una firma: pulsa la <span class="font-semibold text-rose-500">×</span> de su esquina,
                        o seleccionala y pulsa <kbd class="rounded border border-slate-300 px-1">Supr</kbd>.
                    </p>
                    <button data-action="clear-zones" type="button"
                            class="mt-3 w-full rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                        Quitar todas las firmas
                    </button>
                </div>

                <button data-action="apply" type="button"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                    3 · Verificar identidad y firmar
                </button>
                <p class="text-center text-[11px] text-slate-400">Te enviaremos un codigo por email para confirmar la firma.</p>

                <div data-role="result" class="hidden rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center">
                    <p class="text-sm font-semibold text-emerald-800">¡Documento firmado!</p>
                    <a data-role="download" href="#"
                       class="mt-2 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Descargar PDF firmado
                    </a>
                </div>

                <p data-role="status" class="text-center text-xs text-slate-400"></p>
            </aside>
        </div>

        {{-- Modal de verificacion de identidad (OTP por email) --}}
        <div data-role="verify-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-900">Verifica tu identidad</h3>
                    <button data-action="modal-close" type="button" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                {{-- Paso A: datos del firmante --}}
                <div data-role="step-data" class="mt-4 space-y-3">
                    <p class="text-xs text-slate-500">Te enviaremos un codigo por email para confirmar la firma.</p>
                    <input data-role="signer-name" type="text" placeholder="Nombre completo" autocomplete="name"
                           value="{{ $signerName }}" @if($signerName) readonly @endif
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none read-only:bg-slate-100">
                    <input data-role="signer-email" type="email" placeholder="tu@email.com" autocomplete="email"
                           value="{{ $signerEmail }}" @if($signerEmail) readonly @endif
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none read-only:bg-slate-100">
                    <button data-action="send-code" type="button"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        Enviar codigo
                    </button>
                </div>

                {{-- Paso B: codigo OTP --}}
                <div data-role="step-otp" class="mt-4 hidden space-y-3">
                    <p class="text-xs text-slate-500">
                        Introduce el codigo de 6 digitos enviado a
                        <span data-role="sent-email" class="font-medium text-slate-700"></span>.
                    </p>
                    <input data-role="otp-input" inputmode="numeric" maxlength="6" placeholder="------"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-[0.5em] focus:border-indigo-500 focus:outline-none">
                    <button data-action="verify-code" type="button"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                        Verificar y firmar
                    </button>
                    <button data-action="resend-code" type="button"
                            class="w-full text-xs text-slate-400 hover:text-slate-600">Cambiar datos / reenviar</button>
                </div>

                <p data-role="modal-status" class="mt-3 text-center text-xs text-slate-400"></p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/sign.js')
@endpush
