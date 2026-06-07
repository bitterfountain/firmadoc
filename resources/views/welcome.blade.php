@extends('layouts.app')

@section('title', 'FirmaDoc · Firma de documentos')

@section('content')
    <div class="mx-auto mt-6 max-w-3xl">
        <p class="eyebrow">Firma electrónica</p>
        <h1 class="mt-3 text-4xl leading-[1.05] text-ink sm:text-5xl">
            Firma documentos<br><em class="font-normal italic text-accent">con elegancia.</em>
        </h1>
        <p class="mt-4 max-w-xl text-[15px] leading-relaxed text-muted">
            Sube, firma y comparte. Sin fricción para lo cotidiano; con garantías criptográficas
            cuando de verdad importan.
        </p>

        <div class="mt-10 grid gap-5 sm:grid-cols-2">
            {{-- Puerta 1: firma rápida anónima --}}
            <a href="{{ route('quick.start') }}" class="card group relative overflow-hidden p-6 transition-transform duration-200 hover:-translate-y-0.5">
                <span class="grid size-11 place-items-center rounded-xl bg-accent-soft text-accent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                        <path d="M13 2L4.5 13.5H11l-1 8.5L19.5 10H13z"/>
                    </svg>
                </span>
                <h2 class="mt-5 text-xl text-ink">Firma rápida</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">
                    Sin cuenta. Sube, firma en el navegador y recibe el PDF.
                    <span class="text-faint">No se guarda nada en el servidor.</span>
                </p>

                <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-accent-soft px-3 py-1 text-[11px] font-semibold text-accent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M5 13l4 4L19 7"/></svg>
                    Firma electrónica simple (eIDAS)
                </span>
                <p class="mt-2 text-[11px] leading-snug text-faint">Firma visual + sello de integridad SHA-256. Sin verificación de identidad.</p>

                <p class="mt-4 eyebrow">Válida para</p>
                <ul class="mt-2 space-y-1.5 text-sm text-muted">
                    @foreach ([
                        'Consentimientos y autorizaciones',
                        'Recibís, albaranes y conformidades',
                        'Formularios y hojas de firma/asistencia',
                        'Presupuestos y cartas de aceptación',
                    ] as $item)
                        <li class="flex items-start gap-2">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-accent"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <span class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-accent">
                    Firmar ahora
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4 transition-transform group-hover:translate-x-1"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
            </a>

            {{-- Puerta 2: cuenta --}}
            <a href="{{ route('login') }}" class="card group relative overflow-hidden p-6 transition-transform duration-200 hover:-translate-y-0.5">
                <span class="grid size-11 place-items-center rounded-xl text-ink" style="background:rgba(28,25,19,0.06)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                        <path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9.5 12l1.8 1.8L15 10"/>
                    </svg>
                </span>
                <h2 class="mt-5 text-xl text-ink">Cuenta profesional</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">
                    Sello criptográfico (PAdES), multi-firmante y tus documentos guardados y privados.
                </p>

                <span class="mt-4 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold text-ink" style="background:rgba(28,25,19,0.06)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-3.5"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/></svg>
                    Firma avanzada · PAdES
                </span>
                <p class="mt-2 text-[11px] leading-snug text-faint">Verificación por email, sello criptográfico y de tiempo. Cualificada (QES) al cargar un certificado cualificado.</p>

                <p class="mt-4 eyebrow">Pensada para</p>
                <ul class="mt-2 space-y-1.5 text-sm text-muted">
                    @foreach ([
                        'Contratos laborales y mercantiles',
                        'Arrendamientos y acuerdos con terceros',
                        'Facturas y documentos con valor probatorio reforzado',
                        'Firma múltiple con sellado de tiempo (PAdES-T/LTA)',
                    ] as $item)
                        <li class="flex items-start gap-2">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 size-4 shrink-0 text-ink"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>

                <span class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink">
                    Entrar
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4 transition-transform group-hover:translate-x-1"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
            </a>
        </div>

        <p class="mx-auto mt-7 max-w-2xl text-center text-xs leading-relaxed text-faint">
            La <strong class="font-semibold text-muted">firma simple</strong> es válida y admisible para la mayoría de acuerdos privados.
            Para máxima fuerza probatoria o cuando la ley exige forma reforzada, usa la
            <strong class="font-semibold text-muted">firma avanzada o cualificada</strong>.
        </p>
    </div>
@endsection
