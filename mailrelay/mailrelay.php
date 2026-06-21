<?php

/**
 * mailrelay.php — "Resend casero" minimalista.
 *
 * Recibe un POST JSON por HTTPS, valida un token Bearer y envia el correo por
 * SMTP. Pensado para alojarse en un servidor que SI pueda mandar SMTP (p.ej.
 * el propio servidor de correo de leukasoft.com), porque el droplet de
 * DigitalOcean tiene bloqueado el SMTP saliente.
 *
 * Requiere PHPMailer. Instalalo en la misma carpeta con:
 *     composer require phpmailer/phpmailer
 * (o descarga src/ de PHPMailer y ajusta los require de abajo).
 *
 * Contrato (ver App\Mail\Transport\RelayTransport en FirmaDoc):
 *   Headers: Authorization: Bearer <TOKEN>
 *   Body JSON:
 *     {
 *       "from": {"address","name"},
 *       "to":   [{"address","name"}, ...],
 *       "cc": [...], "bcc": [...], "reply_to": [...],
 *       "subject": "...",
 *       "html": "...", "text": "...",
 *       "attachments": [{"filename","content_type","content_base64"}, ...]
 *     }
 *   Respuesta: 200 {"ok":true,"id":"..."}  |  4xx/5xx {"ok":false,"error":"..."}
 */

// ----------------------------------------------------------------------------
// CONFIGURACION  (edita esto)
// ----------------------------------------------------------------------------
$CONFIG = [
    // Token compartido. Genera uno largo:  openssl rand -hex 32
    'token' => getenv('RELAY_TOKEN') ?: 'CAMBIA_ESTE_TOKEN',

    // SMTP del servidor de correo (local). Si el relay corre EN el propio
    // servidor de correo, host=localhost suele bastar.
    'smtp_host' => 'localhost',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',          // 'tls', 'ssl' o '' (sin cifrado, p.ej. localhost:25)
    'smtp_user' => 'no-reply@leukasoft.com',
    'smtp_pass' => 'PON_AQUI_LA_CONTRASENA',

    // Remitente por defecto y dominio permitido (evita open relay).
    'default_from' => ['address' => 'no-reply@leukasoft.com', 'name' => 'FirmaDoc'],
    'allowed_from_domain' => 'leukasoft.com',
];

// Carga de PHPMailer (composer o copia manual de src/).
$autoload = __DIR__.'/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    foreach (['Exception', 'PHPMailer', 'SMTP'] as $c) {
        $f = __DIR__."/src/{$c}.php";
        if (is_file($f)) {
            require $f;
        }
    }
}

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

function fail(int $code, string $msg): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ----------------------------------------------------------------------------
// AUTH
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Method not allowed');
}

$auth = '';
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strcasecmp($k, 'Authorization') === 0) {
            $auth = $v;
        }
    }
}
if ($auth === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
}

$token = preg_match('/Bearer\s+(.+)/i', $auth, $m) ? trim($m[1]) : '';
if ($token === '' || ! hash_equals($CONFIG['token'], $token)) {
    fail(401, 'Unauthorized');
}

// ----------------------------------------------------------------------------
// PARSE
// ----------------------------------------------------------------------------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (! is_array($data)) {
    fail(400, 'Invalid JSON');
}

$to = $data['to'] ?? [];
if (empty($to)) {
    fail(400, 'Missing recipients (to)');
}
if (($data['subject'] ?? '') === '') {
    fail(400, 'Missing subject');
}
if (($data['html'] ?? '') === '' && ($data['text'] ?? '') === '') {
    fail(400, 'Missing body (html/text)');
}

// Remitente: forzamos dominio permitido.
$from = $data['from'] ?? $CONFIG['default_from'];
$fromAddr = $from['address'] ?? $CONFIG['default_from']['address'];
if (substr(strrchr($fromAddr, '@'), 1) !== $CONFIG['allowed_from_domain']) {
    $fromAddr = $CONFIG['default_from']['address'];
}
$fromName = $from['name'] ?? $CONFIG['default_from']['name'];

// ----------------------------------------------------------------------------
// SEND
// ----------------------------------------------------------------------------
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $CONFIG['smtp_host'];
    $mail->Port = (int) $CONFIG['smtp_port'];
    if ($CONFIG['smtp_secure'] !== '') {
        $mail->SMTPSecure = $CONFIG['smtp_secure'];
    } else {
        $mail->SMTPAutoTLS = false;
    }
    if ($CONFIG['smtp_user'] !== '') {
        $mail->SMTPAuth = true;
        $mail->Username = $CONFIG['smtp_user'];
        $mail->Password = $CONFIG['smtp_pass'];
    }
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($fromAddr, $fromName);

    foreach ($to as $r) {
        $mail->addAddress($r['address'], $r['name'] ?? '');
    }
    foreach (($data['cc'] ?? []) as $r) {
        $mail->addCC($r['address'], $r['name'] ?? '');
    }
    foreach (($data['bcc'] ?? []) as $r) {
        $mail->addBCC($r['address'], $r['name'] ?? '');
    }
    foreach (($data['reply_to'] ?? []) as $r) {
        $mail->addReplyTo($r['address'], $r['name'] ?? '');
    }

    $mail->Subject = $data['subject'];
    if (! empty($data['html'])) {
        $mail->isHTML(true);
        $mail->Body = $data['html'];
        if (! empty($data['text'])) {
            $mail->AltBody = $data['text'];
        }
    } else {
        $mail->Body = $data['text'];
    }

    foreach (($data['attachments'] ?? []) as $a) {
        $content = base64_decode($a['content_base64'] ?? '', true);
        if ($content === false) {
            continue;
        }
        $mail->addStringAttachment(
            $content,
            $a['filename'] ?? 'attachment',
            PHPMailer::ENCODING_BASE64,
            $a['content_type'] ?? 'application/octet-stream'
        );
    }

    $mail->send();

    echo json_encode(['ok' => true, 'id' => bin2hex(random_bytes(8))]);
} catch (PHPMailerException $e) {
    fail(502, 'SMTP error: '.$mail->ErrorInfo);
} catch (Throwable $e) {
    fail(500, 'Error: '.$e->getMessage());
}
