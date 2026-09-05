<?php
/**
 * Helpers — funciones utilitarias globales
 * Sistema de Gestión de Carpas y Eventos
 */

/**
 * Escapar output HTML para prevenir XSS
 */
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Formatear monto con moneda del tenant
 */
function formatMonto(float|int|string $monto, string $moneda = 'ARS'): string {
    $simbolos = ['ARS' => '$', 'USD' => 'US$', 'EUR' => '€'];
    $simbolo  = $simbolos[$moneda] ?? $moneda;
    return $simbolo . ' ' . number_format((float)$monto, 2, ',', '.');
}

/**
 * Formatear fecha para mostrar en vistas (d/m/Y H:i)
 */
function formatFecha(string|null $fecha, string $formato = 'd/m/Y H:i'): string {
    if (empty($fecha)) return '—';
    try {
        $dt = new DateTime($fecha);
        return $dt->format($formato);
    } catch (Exception) {
        return $fecha;
    }
}

/**
 * Convertir fecha para input HTML date (Y-m-d)
 */
function fechaParaInput(string|null $fecha): string {
    if (empty($fecha)) return '';
    try {
        return (new DateTime($fecha))->format('Y-m-d');
    } catch (Exception) {
        return '';
    }
}

/**
 * Sanitizar slug: solo letras minúsculas, números y guiones
 */
function sanitizarSlug(string $texto): string {
    $texto = mb_strtolower(trim($texto));
    $texto = preg_replace('/[^a-z0-9\-]/', '', $texto);
    $texto = preg_replace('/-+/', '-', $texto);
    return trim($texto, '-');
}

/**
 * Generar nombre de archivo aleatorio con hash para uploads
 */
function generarNombreArchivo(string $extension): string {
    return bin2hex(random_bytes(16)) . '.' . strtolower($extension);
}

/**
 * Validar tipo MIME real de un archivo subido
 *
 * @param string $tmpPath Ruta temporal del upload
 * @param array  $allowedMimes Array de MIME types permitidos
 */
function validarMimeReal(string $tmpPath, array $allowedMimes): bool {
    if (!file_exists($tmpPath)) return false;
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($tmpPath);
    return in_array($mimeReal, $allowedMimes, true);
}

/**
 * Subir archivo de foto (incidencia, equipo, logo) a storage/uploads.
 * Rechaza si MIME no es imagen o excede tamaño máximo.
 *
 * @param array  $file    Elemento de $_FILES['campo']
 * @param string $subdir  Subdirectorio dentro de storage/uploads (ej: 'equipos')
 * @return array ['success' => bool, 'path' => string|null, 'message' => string]
 */
function subirImagen(array $file, string $subdir = 'misc'): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'path' => null, 'message' => 'Error en la subida del archivo.'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'path' => null, 'message' => 'El archivo supera el tamaño máximo permitido (5 MB).'];
    }

    if (!validarMimeReal($file['tmp_name'], ALLOWED_IMAGE_MIMES)) {
        return ['success' => false, 'path' => null, 'message' => 'Tipo de archivo no permitido.'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombreFinal = generarNombreArchivo($extension);
    $dirDestino  = STORAGE_PATH . '/uploads/' . $subdir;

    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }

    $rutaFinal = $dirDestino . '/' . $nombreFinal;

    if (!move_uploaded_file($file['tmp_name'], $rutaFinal)) {
        return ['success' => false, 'path' => null, 'message' => 'No se pudo guardar el archivo.'];
    }

    return ['success' => true, 'path' => $subdir . '/' . $nombreFinal, 'message' => 'Archivo subido correctamente.'];
}

/**
 * Generar token CSRF y guardarlo en sesión
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF. Lanza excepción si es inválido.
 */
function verificarCsrf(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('CSRF token inválido. Recargá la página e intentá de nuevo.');
    }
}

/**
 * Redirigir a una URL
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Obtener tenant_id de la sesión (nunca confiar en el frontEnd)
 */
function tenantId(): int {
    return (int)($_SESSION['tenant_id'] ?? 0);
}

/**
 * Obtener usuario actual de sesión
 */
function usuarioActual(): array {
    return [
        'id'     => $_SESSION['usuario_id']     ?? null,
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'rol'    => $_SESSION['usuario_rol']    ?? '',
    ];
}

/**
 * URL base de la app con slug del tenant
 */
function urlTenant(string $path = '', ?string $slug = null): string {
    $s = $slug ?? ($_SESSION['tenant_slug'] ?? '');
    $base = rtrim(BASE_URL, '/') . '/' . $s;
    return $path ? ($base . '/' . ltrim($path, '/')) : $base;
}

/**
 * Responder JSON (para endpoints API)
 */
function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Obtener IP real del cliente (considera proxies)
 */
function clienteIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return explode(',', $_SERVER[$key])[0];
        }
    }
    return '0.0.0.0';
}

/**
 * Registrar en audit_log
 */
function auditLog(PDO $pdo, string $accion, string $tabla, ?int $registroId,
                  mixed $datoAntes = null, mixed $datoDespues = null): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO carp_audit_log
             (tenant_id, usuario_id, accion, tabla, registro_id, datos_antes, datos_despues, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            tenantId() ?: null,
            $_SESSION['usuario_id'] ?? null,
            $accion,
            $tabla,
            $registroId,
            $datoAntes  !== null ? json_encode($datoAntes,  JSON_UNESCAPED_UNICODE) : null,
            $datoDespues !== null ? json_encode($datoDespues, JSON_UNESCAPED_UNICODE) : null,
            clienteIp(),
        ]);
    } catch (PDOException $e) {
        error_log('AuditLog error: ' . $e->getMessage());
    }
}

/**
 * Calcular saldo pendiente de un evento
 * Siempre dinámico, nunca campo estático.
 */
function calcularSaldo(PDO $pdo, int $eventoId): array {
    $tid = tenantId();
    $stmtEvento = $pdo->prepare(
        "SELECT precio_total FROM carp_eventos WHERE id = ? AND tenant_id = ? LIMIT 1"
    );
    $stmtEvento->execute([$eventoId, $tid]);
    $evento = $stmtEvento->fetch();

    if (!$evento) return ['precio_total' => 0, 'cobrado' => 0, 'saldo' => 0];

    $stmtPagos = $pdo->prepare(
        "SELECT COALESCE(SUM(monto), 0) as total FROM carp_pagos WHERE evento_id = ? AND tenant_id = ?"
    );
    $stmtPagos->execute([$eventoId, $tid]);
    $cobrado = (float)$stmtPagos->fetchColumn();

    return [
        'precio_total' => (float)$evento['precio_total'],
        'cobrado'      => $cobrado,
        'saldo'        => (float)$evento['precio_total'] - $cobrado,
    ];
}
