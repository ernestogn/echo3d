<?php
function slugify(string $s): string
{
    $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

function pedido(): array
{
    return [
        'ok' => $_GET['msg'] ?? null,
        'err' => $_GET['err'] ?? null,
    ];
}

function subir_imagen(string $campo = 'imagen'): ?string
{
    if (empty($_FILES[$campo]['name']) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $archivo = $_FILES[$campo];
    if ($archivo['size'] > 2 * 1024 * 1024) {
        return null;
    }
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
        return null;
    }
    $nombre = 'prod-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/productos/' . $nombre;
    if (!move_uploaded_file($archivo['tmp_name'], $dest)) {
        return null;
    }
    return $nombre;
}

function validar_precio(string $p): float
{
    return (float) str_replace([',', '.'], '', trim($p));
}