@extends('layouts.app')

@section('title', 'Aviso legal · FirmaDoc')

@section('content')
    <article class="mx-auto max-w-2xl">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Inicio
        </a>

        <p class="eyebrow mt-4">Legal</p>
        <h1 class="mt-2 text-3xl text-ink">Aviso legal</h1>
        <p class="mt-2 text-xs text-faint">Última actualización: 7 de junio de 2026</p>

        <div class="legal mt-8 space-y-6 text-sm leading-relaxed text-muted">
            <section>
                <h2 class="text-lg text-ink">1. Titular del sitio</h2>
                <p class="mt-2">
                    En cumplimiento del deber de información, se hacen constar los datos del titular de
                    <strong class="text-ink">firmadoc.leukasoft.com</strong> (en adelante, «el Sitio» o «FirmaDoc»):
                </p>
                <ul class="mt-2 space-y-1">
                    <li><strong class="text-ink">Titular:</strong> Antonio Sánchez Fernández</li>
                    <li><strong class="text-ink">NIF:</strong> 33499597M</li>
                    <li><strong class="text-ink">Domicilio:</strong> C/ María García Ferrández 30, Bajo Izq, 03201 Elche (Alicante)</li>
                    <li><strong class="text-ink">Teléfono:</strong> 651 189 269</li>
                    <li><strong class="text-ink">Correo de contacto:</strong> info@leukasoft.com</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg text-ink">2. Objeto</h2>
                <p class="mt-2">
                    FirmaDoc es una plataforma que permite firmar electrónicamente documentos. Ofrece una
                    <strong class="text-ink">firma rápida</strong> (firma electrónica simple, sin registro) y, para usuarios
                    registrados, <strong class="text-ink">firma avanzada</strong> con sellado criptográfico (PAdES) y, en su caso,
                    sellado de tiempo.
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">3. Naturaleza del servicio y nivel de firma</h2>
                <ul class="mt-2 space-y-2">
                    <li>FirmaDoc <strong class="text-ink">no es un prestador cualificado de servicios de confianza</strong> (QTSP)
                        en el sentido del Reglamento (UE) 910/2014 (eIDAS).</li>
                    <li>La <strong class="text-ink">firma rápida</strong> es una <strong class="text-ink">firma electrónica simple
                        sin verificación de identidad</strong> (firma manuscrita digitalizada + sello de integridad SHA-256). Es
                        admisible como prueba, pero con valor probatorio limitado; corresponde a las partes valorar su idoneidad.</li>
                    <li>La <strong class="text-ink">firma avanzada (PAdES)</strong> aporta integridad e indicios de autoría y, si se
                        activa, sello de tiempo. La <strong class="text-ink">firma cualificada (QES)</strong> solo se alcanza
                        empleando un certificado cualificado emitido por un QTSP.</li>
                    <li>El titular <strong class="text-ink">no garantiza</strong> que el servicio sea idóneo para actos que exijan una
                        forma legal específica (p. ej. escritura pública). Es responsabilidad del usuario comprobarlo.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg text-ink">4. Condiciones de uso</h2>
                <p class="mt-2">El usuario se compromete a hacer un uso lícito del Sitio y, en particular, a:</p>
                <ul class="mt-2 space-y-1">
                    <li>facilitar datos veraces;</li>
                    <li>firmar únicamente documentos sobre los que tenga derecho, y no suplantar a terceros;</li>
                    <li>no subir contenidos ilícitos, difamatorios o que infrinjan derechos de terceros;</li>
                    <li>no realizar acciones que puedan dañar, sobrecargar o inutilizar el servicio.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg text-ink">5. Exención de responsabilidad</h2>
                <p class="mt-2">
                    El Sitio se presta «tal cual» y «según disponibilidad». El titular no se responsabiliza del uso que los usuarios
                    hagan de las firmas o documentos, de la veracidad de la identidad de los firmantes en la firma simple, ni de los
                    daños derivados de interrupciones, errores o indisponibilidad del servicio, salvo en lo que la ley imponga
                    imperativamente.
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">6. Propiedad intelectual</h2>
                <p class="mt-2">
                    Los elementos del Sitio (marca, diseño, código y contenidos) pertenecen a su titular o a terceros que han
                    autorizado su uso. Los documentos subidos por los usuarios siguen siendo de su exclusiva titularidad.
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">7. Protección de datos</h2>
                <p class="mt-2">
                    El tratamiento de datos personales se rige por la <a href="{{ route('legal.privacy') }}" class="font-semibold text-accent hover:underline">Política de Privacidad</a>.
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">8. Legislación aplicable y jurisdicción</h2>
                <p class="mt-2">
                    Estas condiciones se rigen por la legislación española. Para cualquier controversia, las partes se someten a los
                    juzgados y tribunales del domicilio del titular, salvo que la normativa de consumo disponga otro fuero.
                </p>
            </section>
        </div>
    </article>
@endsection
