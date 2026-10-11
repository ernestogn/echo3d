<?php
declare(strict_types=1);
header('Content-Type: application/json');
$raw = file_get_contents('php://input');
echo json_encode([
  'metodo'       => $_SERVER['REQUEST_METHOD'],
  'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
  'raw_len'      => strlen($raw),
  'raw'          => $raw,
  'json'         => json_decode($raw, true),
  'json_error'   => json_last_error_msg(),
], JSON_UNESCAPED_UNICODE);
