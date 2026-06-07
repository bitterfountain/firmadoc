@extends('layouts.app')

@section('title', 'Política de privacidad · FirmaDoc')

@section('content')
    <article class="mx-auto max-w-2xl">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Inicio
        </a>

        <p class="eyebrow mt-4">Legal</p>
        <h1 class="mt-2 text-3xl text-ink">Política de privacidad</h1>
        <p class="mt-2 text-xs text-faint">Última actualización: 7 de junio de 2026</p>

        <div class="legal mt-8 space-y-6 text-sm leading-relaxed text-muted">
            <section>
                <h2 class="text-lg text-ink">1. Responsable del tratamiento</h2>
                <ul class="mt-2 space-y-1">
                    <li><strong class="text-ink">Responsable:</strong> [COMPLETAR nombre o razón social]</li>
                    <li><strong class="text-ink">NIF/DNI:</strong> [COMPLETAR]</li>
                    <li><strong class="text-ink">Domicilio:</strong> [COMPLETAR]</li>
                    <li><strong class="text-ink">Contacto:</strong> [COMPLETAR, p. ej. privacidad@leukasoft.com]</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg text-ink">2. Qué datos tratamos y con qué fin</h2>
                <p class="mt-2"><strong class="text-ink">a) Firma rápida (sin cuenta).</strong></p>
                <ul class="mt-1 space-y-1">
                    <li>El documento que subes se procesa en un <strong class="text-ink">almacenamiento temporal</strong> y se
                        <strong class="text-ink">elimina automáticamente</strong> tras la firma (a más tardar, en unas horas). No se conserva en el servidor.</li>
                    <li>Si facilitas <strong class="text-ink">nombre y email</strong> (opcional), se usan <strong class="text-ink">solo</strong> para
                        enviarte el PDF firmado; no se conservan una vez enviado.</li>
                    <li>Datos técnicos: dirección IP y datos del navegador, por seguridad del servicio.</li>
                </ul>
                <p class="mt-3"><strong class="text-ink">b) Usuarios registrados.</strong></p>
                <ul class="mt-1 space-y-1">
                    <li>Datos de cuenta: nombre, email y contraseña (almacenada <strong class="text-ink">cifrada</strong>).</li>
                    <li>Documentos y firmas que subes, conservados en tu cuenta.</li>
                    <li>Registro de auditoría de cada firma: nombre, email, IP, navegador, huellas SHA-256 y fecha/hora,
                        con la finalidad de acreditar la firma.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg text-ink">3. Base jurídica</h2>
                <ul class="mt-2 space-y-1">
                    <li><strong class="text-ink">Ejecución del servicio</strong> que solicitas (art. 6.1.b RGPD).</li>
                    <li><strong class="text-ink">Consentimiento</strong> cuando facilitas tu email para recibir el documento (art. 6.1.a).</li>
                    <li><strong class="text-ink">Interés legítimo</strong> en la seguridad y prevención del fraude (art. 6.1.f).</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg text-ink">4. Conservación</h2>
                <p class="mt-2">
                    En la firma rápida, los datos y documentos son <strong class="text-ink">efímeros</strong> y se eliminan tras el proceso.
                    En cuentas registradas, los datos se conservan mientras la cuenta esté activa y, después, durante los plazos legales
                    aplicables; podrás eliminar tus documentos en cualquier momento.
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">5. Destinatarios y encargados</h2>
                <p class="mt-2">No vendemos tus datos. Para prestar el servicio nos apoyamos en:</p>
                <ul class="mt-2 space-y-1">
                    <li><strong class="text-ink">DigitalOcean</strong> — alojamiento, base de datos y almacenamiento de archivos, en centros de datos de la <strong class="text-ink">Unión Europea</strong> (Ámsterdam).</li>
                    <li><strong class="text-ink">Proveedor de correo</strong> para el envío de emails (entrega del PDF, avisos). Este envío puede implicar tratamiento por un proveedor situado <strong class="text-ink">fuera del EEE</strong> (EE. UU.), con las garantías adecuadas (cláusulas contractuales tipo y/o marco de adecuación UE-EE. UU.). [COMPLETAR proveedor concreto]</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg text-ink">6. Transferencias internacionales</h2>
                <p class="mt-2">
                    Cuando un proveedor trate datos fuera del Espacio Económico Europeo, se garantiza un nivel de protección adecuado
                    mediante los mecanismos previstos en el RGPD (decisión de adecuación o cláusulas contractuales tipo).
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">7. Tus derechos</h2>
                <p class="mt-2">
                    Puedes ejercer tus derechos de <strong class="text-ink">acceso, rectificación, supresión, oposición, limitación y
                    portabilidad</strong> escribiendo a [COMPLETAR email de contacto]. Si consideras que tus datos no se tratan
                    correctamente, puedes reclamar ante la <strong class="text-ink">Agencia Española de Protección de Datos</strong>
                    (www.aepd.es).
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">8. Seguridad</h2>
                <p class="mt-2">
                    Aplicamos medidas técnicas y organizativas razonables: conexión cifrada (HTTPS), contraseñas almacenadas con
                    funciones de hash, almacenamiento privado de documentos y eliminación efímera en la firma rápida.
                </p>
            </section>

            <section>
                <h2 class="text-lg text-ink">9. Cookies</h2>
                <p class="mt-2">
                    Solo usamos cookies <strong class="text-ink">técnicas</strong> necesarias para el funcionamiento (sesión y
                    seguridad). No utilizamos cookies de analítica ni de publicidad.
                </p>
            </section>
        </div>
    </article>
@endsection
