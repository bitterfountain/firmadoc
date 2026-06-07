// Pantalla de firma: previsualiza el PDF (PDF.js), deja marcar la zona de
// firma sobre el documento (raton/dedo), captura la firma (signature_pad) y la
// incrusta en el PDF real (pdf-lib) antes de subirlo al servidor.

import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import SignaturePad from 'signature_pad';
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

const root = document.getElementById('signer');
if (root) init(root);

async function init(root) {
    const pdfUrl = root.dataset.pdfUrl;
    const saveUrl = root.dataset.saveUrl;
    const otpUrl = root.dataset.otpUrl;
    const otpVerifyUrl = root.dataset.otpVerifyUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const stage = root.querySelector('[data-role="stage"]');
    const canvas = root.querySelector('[data-role="pdf-canvas"]');
    const overlay = root.querySelector('[data-role="overlay"]');
    const pageLabel = root.querySelector('[data-role="page"]');
    const pagesLabel = root.querySelector('[data-role="pages"]');
    const zoneInfo = root.querySelector('[data-role="zone-info"]');
    const statusEl = root.querySelector('[data-role="status"]');
    const resultBox = root.querySelector('[data-role="result"]');
    const downloadLink = root.querySelector('[data-role="download"]');
    const applyBtn = root.querySelector('[data-action="apply"]');

    // --- Estado ---
    // Guardamos los bytes originales del PDF UNA vez: los usan tanto PDF.js
    // (previsualizar) como pdf-lib (incrustar la firma).
    const pdfBytes = new Uint8Array(await (await fetch(pdfUrl)).arrayBuffer());

    const pdfDoc = await pdfjsLib.getDocument({ data: pdfBytes.slice() }).promise;
    let currentPage = 1;
    let renderTask = null;
    // Lista de firmas colocadas. Cada una: {id, page, x, y, w, h, dataUrl, imgW, imgH}.
    // x/y/w/h en proporciones 0..1 de la pagina; cada firma es independiente.
    const signatures = [];
    let nextId = 1;
    let interaction = null;     // arrastre activo: 'create' | 'move' | 'resize'
    let lastActive = null;      // ultima firma tocada (para borrar con Supr)
    const MIN_PX = 24;          // tamano minimo de una caja de firma (px)

    pagesLabel.textContent = pdfDoc.numPages;

    // --- Render de una pagina ---
    async function renderPage(num) {
        if (renderTask) renderTask.cancel();
        const page = await pdfDoc.getPage(num);

        // Escala que encaje en el ancho disponible (max 900px de render).
        const maxWidth = Math.min(stage.parentElement.clientWidth - 32, 900);
        const base = page.getViewport({ scale: 1 });
        const scale = maxWidth / base.width;
        const viewport = page.getViewport({ scale });

        const outputScale = window.devicePixelRatio || 1;
        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        const ctx = canvas.getContext('2d');
        renderTask = page.render({
            canvasContext: ctx,
            viewport,
            transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null,
        });
        try {
            await renderTask.promise;
        } catch (e) {
            if (e?.name !== 'RenderingCancelledException') throw e;
        }
        renderAllZones();
    }

    // --- Capa de marcado: crear / mover / redimensionar / borrar cajas de firma ---
    function pointerPos(e) {
        const r = overlay.getBoundingClientRect();
        return {
            x: Math.min(Math.max(e.clientX - r.left, 0), r.width),
            y: Math.min(Math.max(e.clientY - r.top, 0), r.height),
        };
    }

    function overlaySize() {
        const r = overlay.getBoundingClientRect();
        return { w: r.width, h: r.height };
    }

    const clamp = (v, min, max) => Math.min(Math.max(v, min), max);

    // Instantanea de la firma actual del pad (o null si esta vacio).
    function currentSignatureSnapshot() {
        if (signaturePad.isEmpty()) return null;
        const t = trimSignature(sigCanvas);
        return { dataUrl: t.dataUrl, imgW: t.width, imgH: t.height };
    }

    function setElRect(el, s) {
        Object.assign(el.style, {
            left: `${s.x * 100}%`, top: `${s.y * 100}%`,
            width: `${s.w * 100}%`, height: `${s.h * 100}%`,
        });
    }

    const CORNERS = [
        ['nw', '-top-1.5 -left-1.5 cursor-nwse-resize'],
        ['ne', '-top-1.5 -right-1.5 cursor-nesw-resize'],
        ['sw', '-bottom-1.5 -left-1.5 cursor-nesw-resize'],
        ['se', '-bottom-1.5 -right-1.5 cursor-nwse-resize'],
    ];

    // Construye el elemento de una firma: caja + imagen + handles + boton borrar.
    function createZoneEl(sig) {
        const el = document.createElement('div');
        el.className = 'absolute border-2 border-dashed border-indigo-400 bg-indigo-500/5 cursor-move touch-none';
        el.dataset.id = sig.id;
        setElRect(el, sig);

        const img = document.createElement('img');
        img.src = sig.dataUrl;
        img.className = 'pointer-events-none absolute inset-0 h-full w-full';
        img.style.objectFit = 'contain';
        el.appendChild(img);

        const del = document.createElement('button');
        del.type = 'button';
        del.textContent = '×';
        del.title = 'Quitar esta firma';
        del.className = 'absolute -top-3 -right-3 z-10 grid size-7 place-items-center rounded-full bg-rose-500 text-lg leading-none text-white shadow ring-2 ring-white hover:bg-rose-600';
        del.addEventListener('pointerdown', (e) => e.stopPropagation());
        del.addEventListener('click', (e) => { e.stopPropagation(); removeSignature(sig.id); });
        el.appendChild(del);

        for (const [name, cls] of CORNERS) {
            const h = document.createElement('div');
            h.className = `absolute size-3 rounded-full border border-white bg-indigo-500 ${cls}`;
            h.dataset.corner = name;
            h.addEventListener('pointerdown', (e) => { e.stopPropagation(); startInteraction(e, 'resize', sig, name); });
            el.appendChild(h);
        }

        // Mover: arrastrar el cuerpo de la caja (no los handles).
        el.addEventListener('pointerdown', (e) => {
            if (e.target.dataset.corner) return;
            e.stopPropagation();
            startInteraction(e, 'move', sig);
        });

        sig._el = el;
        overlay.appendChild(el);
    }

    // Redibuja las cajas de la pagina actual (al cambiar de pagina o escala).
    function renderAllZones() {
        overlay.querySelectorAll('[data-id]').forEach((el) => el.remove());
        for (const sig of signatures) {
            if (sig.page === currentPage) createZoneEl(sig);
        }
    }

    function removeSignature(id) {
        const i = signatures.findIndex((s) => s.id === id);
        if (i === -1) return;
        if (signatures[i] === lastActive) lastActive = null;
        signatures[i]._el?.remove();
        signatures.splice(i, 1);
        updateZoneInfo();
    }

    function updateZoneInfo() {
        const n = signatures.length;
        if (n === 0) {
            zoneInfo.textContent = 'Sin firmas colocadas.';
            zoneInfo.className = 'mt-2 text-xs text-slate-400';
            return;
        }
        const here = signatures.filter((s) => s.page === currentPage).length;
        zoneInfo.textContent = `${n} firma(s) en total${here ? ` · ${here} en esta pagina` : ''}.`;
        zoneInfo.className = 'mt-2 text-xs font-medium text-indigo-600';
    }

    function startInteraction(e, mode, sig, corner = null) {
        overlay.setPointerCapture(e.pointerId);
        setActive(sig);
        const { w: ow, h: oh } = overlaySize();
        interaction = {
            mode, corner, sig,
            startX: e.clientX, startY: e.clientY, ow, oh,
            start: { x: sig.x, y: sig.y, w: sig.w, h: sig.h },
        };
    }

    // Marca una firma como activa y la resalta (anillo indigo).
    function setActive(sig) {
        lastActive = sig;
        for (const s of signatures) {
            s._el?.classList.toggle('ring-2', s === sig);
            s._el?.classList.toggle('ring-indigo-400', s === sig);
        }
    }

    function clearAllZones() {
        [...signatures].forEach((s) => removeSignature(s.id));
        lastActive = null;
    }

    // En zona vacia: empezar una caja nueva usando la firma actual del pad.
    overlay.addEventListener('pointerdown', (e) => {
        const snap = currentSignatureSnapshot();
        if (!snap) { setStatus('Dibuja tu firma antes de colocarla en el documento.', true); return; }
        overlay.setPointerCapture(e.pointerId);
        const p = pointerPos(e);
        const { w: ow, h: oh } = overlaySize();
        const sig = { id: nextId++, page: currentPage, x: p.x / ow, y: p.y / oh, w: 0, h: 0, ...snap };
        signatures.push(sig);
        createZoneEl(sig);
        setActive(sig);
        interaction = { mode: 'create', sig, startPx: { x: p.x, y: p.y }, ow, oh };
    });

    overlay.addEventListener('pointermove', (e) => {
        if (!interaction) return;
        const { mode, sig, ow, oh } = interaction;

        if (mode === 'create') {
            const p = pointerPos(e);
            sig.x = Math.min(interaction.startPx.x, p.x) / ow;
            sig.y = Math.min(interaction.startPx.y, p.y) / oh;
            sig.w = Math.abs(p.x - interaction.startPx.x) / ow;
            sig.h = Math.abs(p.y - interaction.startPx.y) / oh;
        } else if (mode === 'move') {
            const dx = (e.clientX - interaction.startX) / ow;
            const dy = (e.clientY - interaction.startY) / oh;
            sig.x = clamp(interaction.start.x + dx, 0, 1 - interaction.start.w);
            sig.y = clamp(interaction.start.y + dy, 0, 1 - interaction.start.h);
        } else if (mode === 'resize') {
            const p = pointerPos(e);
            const s = interaction.start;
            let x0 = s.x * ow, y0 = s.y * oh, x1 = (s.x + s.w) * ow, y1 = (s.y + s.h) * oh;
            const px = clamp(p.x, 0, ow), py = clamp(p.y, 0, oh);
            const c = interaction.corner;
            if (c.includes('w')) x0 = Math.min(px, x1 - MIN_PX);
            if (c.includes('e')) x1 = Math.max(px, x0 + MIN_PX);
            if (c.includes('n')) y0 = Math.min(py, y1 - MIN_PX);
            if (c.includes('s')) y1 = Math.max(py, y0 + MIN_PX);
            sig.x = x0 / ow; sig.y = y0 / oh; sig.w = (x1 - x0) / ow; sig.h = (y1 - y0) / oh;
        }
        setElRect(sig._el, sig);
    });

    overlay.addEventListener('pointerup', () => {
        if (!interaction) return;
        const { mode, sig } = interaction;
        interaction = null;
        if (mode === 'create') {
            const { w: ow, h: oh } = overlaySize();
            // Caja demasiado pequena (un toque) -> se descarta.
            if (sig.w * ow < MIN_PX || sig.h * oh < MIN_PX) { removeSignature(sig.id); return; }
        }
        updateZoneInfo();
    });

    // --- Navegacion ---
    root.querySelector('[data-action="prev"]').addEventListener('click', () => {
        if (currentPage > 1) { currentPage--; pageLabel.textContent = currentPage; renderPage(currentPage); }
    });
    root.querySelector('[data-action="next"]').addEventListener('click', () => {
        if (currentPage < pdfDoc.numPages) { currentPage++; pageLabel.textContent = currentPage; renderPage(currentPage); }
    });

    // --- Signature pad ---
    const sigCanvas = root.querySelector('[data-role="sigpad"]');
    function sizeSigPad() {
        const ratio = window.devicePixelRatio || 1;
        sigCanvas.width = sigCanvas.offsetWidth * ratio;
        sigCanvas.height = sigCanvas.offsetHeight * ratio;
        sigCanvas.getContext('2d').scale(ratio, ratio);
    }
    sizeSigPad();
    const signaturePad = new SignaturePad(sigCanvas, { penColor: '#0f172a', backgroundColor: 'rgba(0,0,0,0)' });
    // El pad es la "firma actual": cada caja que coloques captura una instantanea.
    // Limpiar solo vacia el pad; las firmas ya colocadas no se tocan.
    root.querySelector('[data-action="clear-sig"]').addEventListener('click', () => signaturePad.clear());

    // Quitar todas las firmas colocadas.
    root.querySelector('[data-action="clear-zones"]')?.addEventListener('click', clearAllZones);

    // Tecla Supr/Backspace: borra la firma activa (la ultima tocada).
    window.addEventListener('keydown', (e) => {
        if (e.key !== 'Delete' && e.key !== 'Backspace') return;
        if (!lastActive || !signatures.includes(lastActive)) return;
        e.preventDefault();
        removeSignature(lastActive.id);
    });

    // --- Flujo de firma con verificacion OTP (Nivel 1) ---
    const modal = root.querySelector('[data-role="verify-modal"]');
    const stepData = modal.querySelector('[data-role="step-data"]');
    const stepOtp = modal.querySelector('[data-role="step-otp"]');
    const nameInput = modal.querySelector('[data-role="signer-name"]');
    const emailInput = modal.querySelector('[data-role="signer-email"]');
    const otpInput = modal.querySelector('[data-role="otp-input"]');
    const sentEmail = modal.querySelector('[data-role="sent-email"]');
    const modalStatus = modal.querySelector('[data-role="modal-status"]');
    let eventId = null;

    const setModalStatus = (msg, isError = false) => {
        modalStatus.textContent = msg;
        modalStatus.className = `mt-3 text-center text-xs ${isError ? 'text-rose-500' : 'text-slate-400'}`;
    };
    const openModal = () => {
        stepData.classList.remove('hidden');
        stepOtp.classList.add('hidden');
        setModalStatus('');
        modal.classList.replace('hidden', 'flex');
    };
    const closeModal = () => modal.classList.replace('flex', 'hidden');

    const postJson = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        body: JSON.stringify(body),
    });

    // "Verificar identidad y firmar" -> abre el modal (todavia no firma).
    applyBtn.addEventListener('click', () => {
        if (!signatures.length) return setStatus('Coloca al menos una firma en el documento.', true);
        openModal();
    });
    modal.querySelector('[data-action="modal-close"]').addEventListener('click', closeModal);
    modal.querySelector('[data-action="resend-code"]').addEventListener('click', () => {
        stepOtp.classList.add('hidden');
        stepData.classList.remove('hidden');
        setModalStatus('');
    });

    // Paso 1: solicitar el codigo OTP.
    modal.querySelector('[data-action="send-code"]').addEventListener('click', async (e) => {
        const signer_name = nameInput.value.trim();
        const signer_email = emailInput.value.trim();
        if (!signer_name || !signer_email) return setModalStatus('Rellena nombre y email.', true);

        e.target.disabled = true;
        setModalStatus('Enviando codigo...');
        try {
            const res = await postJson(otpUrl, { signer_name, signer_email });
            const json = await res.json();
            if (!res.ok) throw new Error(json.message || `Error ${res.status}`);
            eventId = json.event_id;
            sentEmail.textContent = signer_email;
            stepData.classList.add('hidden');
            stepOtp.classList.remove('hidden');
            otpInput.value = '';
            otpInput.focus();
            setModalStatus('Codigo enviado. Revisa tu email.');
        } catch (err) {
            setModalStatus(err.message, true);
        } finally {
            e.target.disabled = false;
        }
    });

    // Paso 2: verificar el codigo -> incrustar firmas + certificado -> subir.
    modal.querySelector('[data-action="verify-code"]').addEventListener('click', async (e) => {
        const otp = otpInput.value.trim();
        if (otp.length !== 6) return setModalStatus('Introduce los 6 digitos.', true);

        e.target.disabled = true;
        setModalStatus('Verificando...');
        try {
            const res = await postJson(otpVerifyUrl, { event_id: eventId, otp });
            const json = await res.json();
            if (!res.ok) {
                const extra = json.remaining != null ? ` (${json.remaining} intentos)` : '';
                throw new Error((json.message || 'Codigo incorrecto') + extra);
            }

            setModalStatus('Firmando e incrustando certificado...');
            const signedBytes = await buildSignedPdf(json.audit);

            const form = new FormData();
            form.append('event_id', eventId);
            form.append('signed', new Blob([signedBytes], { type: 'application/pdf' }), 'signed.pdf');
            const up = await fetch(saveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: form,
            });
            const upJson = await up.json();
            if (!up.ok) throw new Error(upJson.message || `Error ${up.status}`);

            closeModal();
            downloadLink.href = upJson.download_url;
            resultBox.classList.remove('hidden');
            setStatus('');
        } catch (err) {
            console.error(err);
            setModalStatus(err.message, true);
        } finally {
            e.target.disabled = false;
        }
    });

    // Construye el PDF firmado: incrusta todas las firmas + anexa el certificado.
    async function buildSignedPdf(audit) {
        const out = await PDFDocument.load(pdfBytes.slice());
        const pages = out.getPages();

        for (const sig of signatures) {
            const page = pages[sig.page - 1];
            if (!page) continue;
            const { width: pw, height: ph } = page.getSize();
            const png = await out.embedPng(sig.dataUrl);
            const boxW = sig.w * pw, boxH = sig.h * ph;
            const boxX = sig.x * pw, boxYTop = sig.y * ph;
            const fit = Math.min(boxW / sig.imgW, boxH / sig.imgH);
            const drawW = sig.imgW * fit, drawH = sig.imgH * fit;
            const drawX = boxX + (boxW - drawW) / 2;
            const drawYTop = boxYTop + (boxH - drawH) / 2;
            page.drawImage(png, { x: drawX, y: ph - drawYTop - drawH, width: drawW, height: drawH });
        }

        await appendCertificate(out, audit);
        return out.save();
    }

    // Anexa una pagina-certificado A4 con el registro de auditoria.
    async function appendCertificate(doc, audit) {
        const page = doc.addPage([595, 842]);
        const font = await doc.embedFont(StandardFonts.Helvetica);
        const bold = await doc.embedFont(StandardFonts.HelveticaBold);
        const ink = rgb(0.12, 0.16, 0.23);
        const muted = rgb(0.45, 0.5, 0.58);

        let y = 780;
        page.drawText('Certificado de firma electronica', { x: 50, y, size: 18, font: bold, color: ink });
        y -= 14;
        page.drawLine({ start: { x: 50, y }, end: { x: 545, y }, thickness: 1, color: rgb(0.85, 0.87, 0.9) });
        y -= 32;

        const rows = [
            ['Referencia', audit.reference],
            ['Firmante', audit.signer_name],
            ['Email verificado', audit.signer_email],
            ['Fecha y hora (verificacion)', audit.verified_at_human],
            ['Direccion IP', audit.ip_address || '-'],
            ['Hash SHA-256 del documento', audit.document_hash],
            ['Numero de firmas incrustadas', String(signatures.length)],
        ];

        for (const [label, value] of rows) {
            page.drawText(label.toUpperCase(), { x: 50, y, size: 8, font: bold, color: muted });
            y -= 15;
            for (const chunk of String(value).match(/.{1,72}/g) || ['']) {
                page.drawText(chunk, { x: 50, y, size: 11, font, color: ink });
                y -= 16;
            }
            y -= 8;
        }

        page.drawText('Documento firmado con FirmaDoc. Firma electronica simple con verificacion de identidad por email.', {
            x: 50, y: 56, size: 8, font, color: muted,
        });
    }

    function setStatus(msg, isError = false) {
        statusEl.textContent = msg;
        statusEl.className = `text-center text-xs ${isError ? 'text-rose-500' : 'text-slate-400'}`;
    }

    // Primer render
    await renderPage(currentPage);
    updateZoneInfo();
    window.addEventListener('resize', () => renderPage(currentPage));
}

/**
 * Recorta el canvas de la firma a su bounding box opaco y devuelve
 * { dataUrl, width, height } del recorte (para conservar proporcion).
 */
function trimSignature(canvas) {
    const ctx = canvas.getContext('2d');
    const { width, height } = canvas;
    const { data } = ctx.getImageData(0, 0, width, height);

    let minX = width, minY = height, maxX = 0, maxY = 0, found = false;
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > 8) {
                found = true;
                if (x < minX) minX = x;
                if (x > maxX) maxX = x;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
            }
        }
    }
    if (!found) return { dataUrl: canvas.toDataURL('image/png'), width, height };

    const pad = 6;
    minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
    maxX = Math.min(width, maxX + pad); maxY = Math.min(height, maxY + pad);
    const w = maxX - minX, h = maxY - minY;

    const out = document.createElement('canvas');
    out.width = w; out.height = h;
    out.getContext('2d').drawImage(canvas, minX, minY, w, h, 0, 0, w, h);
    return { dataUrl: out.toDataURL('image/png'), width: w, height: h };
}
