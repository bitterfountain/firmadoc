@extends('layouts.app')

@section('title', __('Política de privacidad') . ' · FirmaDoc')

@section('content')
    <article class="mx-auto max-w-2xl">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm text-muted transition-colors hover:text-ink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            {{ __('Inicio') }}
        </a>

        <p class="eyebrow mt-4">{{ __('Legal') }}</p>
        <h1 class="mt-2 text-3xl text-ink">{{ __('Política de privacidad') }}</h1>
        <p class="mt-2 text-xs text-faint">{{ __('Última actualización: 8 de junio de 2026') }}</p>

        <div class="mt-8 space-y-6 text-sm leading-relaxed text-muted">
            @if (app()->getLocale() === 'en')
                <section>
                    <h2 class="text-lg text-ink">1. Data controller</h2>
                    <ul class="mt-2 space-y-1">
                        <li><strong class="text-ink">Controller:</strong> Antonio Sánchez Fernández</li>
                        <li><strong class="text-ink">Tax ID (NIF):</strong> 33499597M</li>
                        <li><strong class="text-ink">Address:</strong> C/ María García Ferrández 30, Bajo Izq, 03201 Elche (Alicante), Spain</li>
                        <li><strong class="text-ink">Contact:</strong> info@leukasoft.com</li>
                    </ul>
                </section>
                <section>
                    <h2 class="text-lg text-ink">2. What data we process and why</h2>
                    <p class="mt-2"><strong class="text-ink">a) Quick signature (no account).</strong></p>
                    <ul class="mt-1 space-y-1">
                        <li>The document you upload is processed in <strong class="text-ink">temporary storage</strong> and <strong class="text-ink">automatically deleted</strong> after signing (at most within a few hours). It is not retained on the server.</li>
                        <li>If you provide a <strong class="text-ink">name and email</strong> (optional), they are used <strong class="text-ink">only</strong> to send you the signed PDF; they are not kept afterwards.</li>
                        <li>Technical data: IP address and browser data, for service security.</li>
                    </ul>
                    <p class="mt-3"><strong class="text-ink">b) Registered users.</strong></p>
                    <ul class="mt-1 space-y-1">
                        <li>Account data: name, email and password (stored <strong class="text-ink">encrypted</strong>).</li>
                        <li>Documents and signatures you upload, kept in your account.</li>
                        <li>An audit record of each signature: name, email, IP, browser, SHA-256 hashes and timestamp, to evidence the signature.</li>
                    </ul>
                </section>
                <section>
                    <h2 class="text-lg text-ink">3. Legal basis</h2>
                    <ul class="mt-2 space-y-1">
                        <li><strong class="text-ink">Performance of the service</strong> you request (Art. 6.1.b GDPR).</li>
                        <li><strong class="text-ink">Consent</strong> when you provide your email to receive the document (Art. 6.1.a).</li>
                        <li><strong class="text-ink">Legitimate interest</strong> in security and fraud prevention (Art. 6.1.f).</li>
                    </ul>
                </section>
                <section>
                    <h2 class="text-lg text-ink">4. Retention</h2>
                    <p class="mt-2">In the quick signature, data and documents are <strong class="text-ink">ephemeral</strong> and deleted after the process. For registered accounts, data is kept while the account is active and, afterwards, for the applicable legal periods; you can delete your documents at any time.</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">5. Recipients and processors</h2>
                    <p class="mt-2">We do not sell your data. To run the service we rely on:</p>
                    <ul class="mt-2 space-y-1">
                        <li><strong class="text-ink">DigitalOcean</strong> — hosting, database and file storage, in <strong class="text-ink">European Union</strong> data centres (Amsterdam).</li>
                        <li><strong class="text-ink">Own mail server</strong> (leukasoft.com domain) for sending emails, located in the <strong class="text-ink">European Union</strong>.</li>
                    </ul>
                </section>
                <section>
                    <h2 class="text-lg text-ink">6. International transfers</h2>
                    <p class="mt-2">Data is processed on servers located in the <strong class="text-ink">European Union</strong>. No international transfers outside the EEA take place. Should it become necessary in the future, an adequate level of protection would be ensured through the mechanisms provided by the GDPR.</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">7. Your rights</h2>
                    <p class="mt-2">You may exercise your rights of <strong class="text-ink">access, rectification, erasure, objection, restriction and portability</strong> by writing to info@leukasoft.com. If you believe your data is not handled properly, you may lodge a complaint with the <strong class="text-ink">Spanish Data Protection Agency</strong> (www.aepd.es).</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">8. Security</h2>
                    <p class="mt-2">We apply reasonable technical and organisational measures: encrypted connection (HTTPS), passwords stored with hash functions, private document storage and ephemeral deletion in the quick signature.</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">9. Cookies</h2>
                    <p class="mt-2">We only use <strong class="text-ink">technical</strong> cookies needed for operation (session and security). We do not use analytics or advertising cookies.</p>
                </section>
            @else
                <section>
                    <h2 class="text-lg text-ink">1. Responsable del tratamiento</h2>
                    <ul class="mt-2 space-y-1">
                        <li><strong class="text-ink">Responsable:</strong> Antonio Sánchez Fernández</li>
                        <li><strong class="text-ink">NIF:</strong> 33499597M</li>
                        <li><strong class="text-ink">Domicilio:</strong> C/ María García Ferrández 30, Bajo Izq, 03201 Elche (Alicante)</li>
                        <li><strong class="text-ink">Contacto:</strong> info@leukasoft.com</li>
                    </ul>
                </section>
                <section>
                    <h2 class="text-lg text-ink">2. Qué datos tratamos y con qué fin</h2>
                    <p class="mt-2"><strong class="text-ink">a) Firma rápida (sin cuenta).</strong></p>
                    <ul class="mt-1 space-y-1">
                        <li>El documento que subes se procesa en un <strong class="text-ink">almacenamiento temporal</strong> y se <strong class="text-ink">elimina automáticamente</strong> tras la firma (a más tardar, en unas horas). No se conserva en el servidor.</li>
                        <li>Si facilitas <strong class="text-ink">nombre y email</strong> (opcional), se usan <strong class="text-ink">solo</strong> para enviarte el PDF firmado; no se conservan una vez enviado.</li>
                        <li>Datos técnicos: dirección IP y datos del navegador, por seguridad del servicio.</li>
                    </ul>
                    <p class="mt-3"><strong class="text-ink">b) Usuarios registrados.</strong></p>
                    <ul class="mt-1 space-y-1">
                        <li>Datos de cuenta: nombre, email y contraseña (almacenada <strong class="text-ink">cifrada</strong>).</li>
                        <li>Documentos y firmas que subes, conservados en tu cuenta.</li>
                        <li>Registro de auditoría de cada firma: nombre, email, IP, navegador, huellas SHA-256 y fecha/hora, con la finalidad de acreditar la firma.</li>
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
                    <p class="mt-2">En la firma rápida, los datos y documentos son <strong class="text-ink">efímeros</strong> y se eliminan tras el proceso. En cuentas registradas, los datos se conservan mientras la cuenta esté activa y, después, durante los plazos legales aplicables; podrás eliminar tus documentos en cualquier momento.</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">5. Destinatarios y encargados</h2>
                    <p class="mt-2">No vendemos tus datos. Para prestar el servicio nos apoyamos en:</p>
                    <ul class="mt-2 space-y-1">
                        <li><strong class="text-ink">DigitalOcean</strong> — alojamiento, base de datos y almacenamiento de archivos, en centros de datos de la <strong class="text-ink">Unión Europea</strong> (Ámsterdam).</li>
                        <li><strong class="text-ink">Servidor de correo propio</strong> (dominio leukasoft.com) para el envío de emails, ubicado en la <strong class="text-ink">Unión Europea</strong>.</li>
                    </ul>
                </section>
                <section>
                    <h2 class="text-lg text-ink">6. Transferencias internacionales</h2>
                    <p class="mt-2">Los datos se tratan en servidores ubicados en la <strong class="text-ink">Unión Europea</strong>. No se realizan transferencias internacionales fuera del Espacio Económico Europeo. Si en el futuro fuera necesario, se garantizaría un nivel de protección adecuado mediante los mecanismos previstos en el RGPD.</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">7. Tus derechos</h2>
                    <p class="mt-2">Puedes ejercer tus derechos de <strong class="text-ink">acceso, rectificación, supresión, oposición, limitación y portabilidad</strong> escribiendo a info@leukasoft.com. Si consideras que tus datos no se tratan correctamente, puedes reclamar ante la <strong class="text-ink">Agencia Española de Protección de Datos</strong> (www.aepd.es).</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">8. Seguridad</h2>
                    <p class="mt-2">Aplicamos medidas técnicas y organizativas razonables: conexión cifrada (HTTPS), contraseñas almacenadas con funciones de hash, almacenamiento privado de documentos y eliminación efímera en la firma rápida.</p>
                </section>
                <section>
                    <h2 class="text-lg text-ink">9. Cookies</h2>
                    <p class="mt-2">Solo usamos cookies <strong class="text-ink">técnicas</strong> necesarias para el funcionamiento (sesión y seguridad). No utilizamos cookies de analítica ni de publicidad.</p>
                </section>
            @endif
        </div>
    </article>
@endsection
