<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function wa_link(string $text): string
{
    return 'https://wa.me/' . SITE_WHATSAPP . '?text=' . rawurlencode($text);
}

function notificar_contacto(array $m): void
{
    $subject = '=?UTF-8?B?' . base64_encode('Nuevo mensaje de contacto — ' . SITE_NOMBRE) . '?=';
    $body = 'Nombre: ' . $m['nombre'] . "\n"
        . 'Email: ' . $m['email'] . "\n"
        . 'Teléfono: ' . ($m['telefono'] ?: '-') . "\n\n"
        . "Mensaje:\n" . $m['mensaje'] . "\n\n"
        . 'IP: ' . ($m['ip'] ?? '-');
    $headers = [
        'From: ' . SITE_MAIL_FROM,
        'Reply-To: ' . $m['email'],
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    @mail(SITE_NOTIF_EMAIL, $subject, base64_encode($body), implode("\r\n", $headers));
}