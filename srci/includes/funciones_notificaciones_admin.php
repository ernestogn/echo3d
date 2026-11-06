<?php
declare(strict_types=1);

// Funciones para notificaciones a administradores
// Añadido para la funcionalidad de notificación por incidente y digest diario

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/funciones.php';

/**
 * Obtiene todos los administradores con notificación por incidente habilitada
 */
function obtener_admins_con_notificacion_incidente(): array
{
    try {
        $st = db()->query(
            "SELECT id, nombre, email FROM usuarios 
             WHERE rol = 'admin' AND activo = 1 
             AND (notifica_incidentes_admin = 1 OR notifica_incidentes_admin IS NULL)"
        );
        return $st->fetchAll();
    } catch (Throwable $e) {
        // Si las columnas no existen aún, intentar sin filtro
        try {
            $st = db()->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'admin' AND activo = 1");
            return $st->fetchAll();
        } catch (Throwable $e2) {
            return [];
        }
    }
}

/**
 * Obtiene todos los administradores con notificación digest habilitada
 */
function obtener_admins_con_notificacion_digest(): array
{
    try {
        $st = db()->query(
            "SELECT id, nombre, email FROM usuarios 
             WHERE rol = 'admin' AND activo = 1 
             AND (notifica_digest_admin = 1 OR notifica_digest_admin IS NULL)"
        );
        return $st->fetchAll();
    } catch (Throwable $e) {
        try {
            $st = db()->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'admin' AND activo = 1");
            return $st->fetchAll();
        } catch (Throwable $e2) {
            return [];
        }
    }
}

/**
 * Notifica a todos los administradores sobre un nuevo incidente
 */
function notificar_administradores_nuevo_incidente(int $incidencia_id): bool
{
    $admins = obtener_admins_con_notificacion_incidente();
    if (empty($admins)) {
        return false;
    }
    
    try {
        $st = db()->prepare(
            'SELECT i.id, i.usuario_id, i.direccion, i.gravedad, i.estado,
                    t.nombre AS tipo_nombre, t.icono AS tipo_icono,
                    b.nombre AS barrio_nombre,
                    u.nombre AS creador_nombre
             FROM incidencias i
             JOIN tipos_incidencia t ON t.id = i.tipo_id
             LEFT JOIN barrios b     ON b.id = i.barrio_id
             JOIN usuarios u         ON u.id = i.usuario_id
             WHERE i.id = :id LIMIT 1'
        );
        $st->execute([':id' => $incidencia_id]);
        $inc = $st->fetch();
        
        if (!$inc) {
            return false;
        }
    } catch (Throwable $e) {
        return false;
    }
    
    $enviados = 0;
    foreach ($admins as $admin) {
        if (!email_ruteable($admin['email'] ?? null)) {
            continue;
        }
        if (enviar_email_nuevo_incidente_admin($inc, $admin)) {
            $enviados++;
        }
    }
    
    return $enviados > 0;
}

/**
 * Envía email a un administrador sobre un nuevo incidente
 */
function enviar_email_nuevo_incidente_admin(array $inc, array $admin): bool
{
    $asunto = 'Nuevo incidente #' . $inc['id'] . ' — SRCI';
    $url = 'https://echo3dlaser.com.ar/srci/detalle.php?id=' . $inc['id'];
    
    $creador = $inc['creador_nombre'] ?? 'Usuario';
    $tipo = trim(($inc['tipo_icono'] ?? '') . ' ' . ($inc['tipo_nombre'] ?? ''));
    $direccion = ($inc['direccion'] ?? '') !== '' ? $inc['direccion'] : '—';
    $barrio = ($inc['barrio_nombre'] ?? '') !== '' ? $inc['barrio_nombre'] : '—';
    $gravedad = nombre_gravedad($inc['gravedad'] ?? null);
    $estado = nombre_estado($inc['estado'] ?? 'pendiente');
    
    $texto = "Hola {$admin['nombre']},\n\n"
           . "Se ha registrado un nuevo incidente en el sistema.\n"
           . "Reportado por: {$creador}\n\n"
           . "Resumen del incidente #{$inc['id']}:\n"
           . "- Tipo: {$tipo}\n"
           . "- Dirección: {$direccion}\n"
           . "- Barrio: {$barrio}\n"
           . "- Gravedad: {$gravedad}\n"
           . "- Estado: {$estado}\n\n"
           . "Ver el reporte: {$url}\n\n"
           . "Mensaje automático del SRCI — no respondas este email.\n";

    // Valores escapados para la parte HTML (regla: toda salida HTML usa esc())
    $admin_html     = esc((string)$admin['nombre']);
    $creador_html   = esc((string)$creador);
    $tipo_html      = esc((string)$tipo);
    $direccion_html = esc((string)$direccion);
    $barrio_html    = esc((string)$barrio);
    $gravedad_html  = esc((string)$gravedad);
    $estado_html    = esc((string)$estado);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#dc2626;padding:26px 32px;text-align:center;">
          <div style="font-size:30px;line-height:1;">🚨</div>
          <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;margin-top:6px;">SRCI - Nuevo Incidente</div>
          <div style="color:#fecaca;font-size:13px;margin-top:2px;">Notificación para Administrador</div>
        </td></tr>
        <tr><td style="padding:30px 32px;">
          <p style="margin:0 0 8px;color:#0f172a;font-size:17px;font-weight:700;">Hola {$admin_html} 👋</p>
          <p style="margin:0 0 16px;color:#475569;font-size:14px;line-height:1.6;">Se ha registrado un nuevo incidente en el sistema.</p>
          <p style="margin:0 0 16px;color:#475569;font-size:13px;line-height:1.6;"><strong>Reportado por:</strong> {$creador_html}</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:20px;">
            <tr><td style="padding:8px 12px;color:#64748b;font-size:13px;font-weight:700;border-bottom:1px solid #f1f5f9;white-space:nowrap;">Tipo</td><td style="padding:8px 12px;color:#0f172a;font-size:13px;border-bottom:1px solid #f1f5f9;">{$tipo_html}</td></tr>
            <tr><td style="padding:8px 12px;color:#64748b;font-size:13px;font-weight:700;border-bottom:1px solid #f1f5f9;white-space:nowrap;">Dirección</td><td style="padding:8px 12px;color:#0f172a;font-size:13px;border-bottom:1px solid #f1f5f9;">{$direccion_html}</td></tr>
            <tr><td style="padding:8px 12px;color:#64748b;font-size:13px;font-weight:700;border-bottom:1px solid #f1f5f9;white-space:nowrap;">Barrio</td><td style="padding:8px 12px;color:#0f172a;font-size:13px;border-bottom:1px solid #f1f5f9;">{$barrio_html}</td></tr>
            <tr><td style="padding:8px 12px;color:#64748b;font-size:13px;font-weight:700;border-bottom:1px solid #f1f5f9;white-space:nowrap;">Gravedad</td><td style="padding:8px 12px;color:#0f172a;font-size:13px;border-bottom:1px solid #f1f5f9;">{$gravedad_html}</td></tr>
            <tr><td style="padding:8px 12px;color:#64748b;font-size:13px;font-weight:700;">Estado</td><td style="padding:8px 12px;color:#0f172a;font-size:13px;">{$estado_html}</td></tr>
          </table>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="{$url}" style="display:inline-block;background:#dc2626;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 34px;border-radius:10px;">Ver el reporte</a>
            </td></tr>
          </table>
          <p style="margin:18px 0 0;color:#64748b;font-size:12px;line-height:1.6;">Si el botón no funciona, copiá este enlace:<br><a href="{$url}" style="color:#dc2626;">{$url}</a></p>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">Mensaje automático del Sistema de Reporte Ciudadano — echo3dlaser.com.ar. No respondas este email.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    
    $limite = md5(uniqid((string)microtime(), true));
    $headers = implode("\r\n", [
        'From: SRCI <admin@echo3dlaser.com.ar>',
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$limite}\"",
    ]);
    
    $cuerpo = "--{$limite}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texto)) . "\r\n"
            . "--{$limite}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$limite}--\r\n";
    
    return @mail($admin['email'], '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $headers, '-fadmin@echo3dlaser.com.ar');
}

/**
 * Envía digest diario a administradores
 */
function enviar_digest_diario_admin(): bool
{
    $admins = obtener_admins_con_notificacion_digest();
    if (empty($admins)) {
        return false;
    }
    
    try {
        $st = db()->query(
            "SELECT i.id, i.direccion, i.gravedad, i.estado, i.fecha_hora,
                    t.nombre AS tipo_nombre, t.icono AS tipo_icono,
                    b.nombre AS barrio_nombre,
                    u.nombre AS creador_nombre
             FROM incidencias i
             JOIN tipos_incidencia t ON t.id = i.tipo_id
             LEFT JOIN barrios b     ON b.id = i.barrio_id
             JOIN usuarios u         ON u.id = i.usuario_id
             WHERE i.fecha_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND i.aprobado = 1
               AND i.oculto = 0
             ORDER BY i.fecha_hora DESC"
        );
        $incidencias = $st->fetchAll();
    } catch (Throwable $e) {
        try {
            $st = db()->query(
                "SELECT i.id, i.direccion, i.gravedad, i.estado, i.fecha_hora,
                        t.nombre AS tipo_nombre, t.icono AS tipo_icono,
                        b.nombre AS barrio_nombre,
                        u.nombre AS creador_nombre
                 FROM incidencias i
                 JOIN tipos_incidencia t ON t.id = i.tipo_id
                 LEFT JOIN barrios b     ON b.id = i.barrio_id
                 JOIN usuarios u         ON u.id = i.usuario_id
                 WHERE i.fecha_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   AND i.oculto = 0
                 ORDER BY i.fecha_hora DESC"
            );
            $incidencias = $st->fetchAll();
        } catch (Throwable $e2) {
            return false;
        }
    }
    
    if (empty($incidencias)) {
        return false;
    }
    
    $enviados = 0;
    foreach ($admins as $admin) {
        if (!email_ruteable($admin['email'] ?? null)) {
            continue;
        }
        if (enviar_email_digest_admin($incidencias, $admin, 'en las últimas 24 horas')) {
            $enviados++;
        }
    }
    
    return $enviados > 0;
}

/**
 * Envía email digest a un administrador
 */
function enviar_email_digest_admin(array $incidencias, array $admin, string $ventana = 'en las últimas 24 horas'): bool
{
    $cantidad = count($incidencias);
    $admin_html = esc((string)$admin['nombre']);
    $ventana_html = esc($ventana);
    $asunto = "Digest diario SRCI ({$ventana}) - {$cantidad} incidente" . ($cantidad > 1 ? 's' : '');
    $url = 'https://echo3dlaser.com.ar/srci/incidencias.php';
    
    $fecha = date('d/m/Y');
    
    $texto = "Hola {$admin['nombre']},\n\n"
           . "Resumen diario de incidencias del SRCI - {$fecha}\n\n"
           . "Se registraron {$cantidad} incidente" . ($cantidad > 1 ? 's' : '') . " {$ventana}:\n\n";
    
    foreach ($incidencias as $inc) {
        $tipo = trim(($inc['tipo_icono'] ?? '') . ' ' . ($inc['tipo_nombre'] ?? ''));
        $texto .= "- #{$inc['id']} {$tipo} | {$inc['direccion']} | {$inc['barrio_nombre']} | " . nombre_gravedad($inc['gravedad'] ?? null) . "\n";
    }
    
    $texto .= "\nVer todas las incidencias: {$url}\n\n"
            . "Mensaje automático del SRCI — no respondas este email.\n";
    
    $filas_html = '';
    foreach ($incidencias as $inc) {
        $tipo = trim(($inc['tipo_icono'] ?? '') . ' ' . ($inc['tipo_nombre'] ?? ''));
        $gravedad = nombre_gravedad($inc['gravedad'] ?? null);
        $filas_html .= '<tr>'
            . '<td style="padding:8px 10px;color:#0f172a;font-size:12px;border-bottom:1px solid #f1f5f9;">#' . esc((string)$inc['id']) . '</td>'
            . '<td style="padding:8px 10px;color:#0f172a;font-size:12px;border-bottom:1px solid #f1f5f9;">' . esc((string)$tipo) . '</td>'
            . '<td style="padding:8px 10px;color:#475569;font-size:12px;border-bottom:1px solid #f1f5f9;">' . esc((string)($inc['direccion'] ?? '—')) . '</td>'
            . '<td style="padding:8px 10px;color:#475569;font-size:12px;border-bottom:1px solid #f1f5f9;">' . esc((string)($inc['barrio_nombre'] ?? '—')) . '</td>'
            . '<td style="padding:8px 10px;color:#475569;font-size:12px;border-bottom:1px solid #f1f5f9;">' . esc($gravedad) . '</td>'
            . '</tr>';
    }
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:650px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#2563eb;padding:26px 32px;text-align:center;">
          <div style="font-size:30px;line-height:1;">📊</div>
          <div style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;margin-top:6px;">SRCI - Digest Diario</div>
          <div style="color:#bfdbfe;font-size:13px;margin-top:2px;">{$fecha}</div>
        </td></tr>
        <tr><td style="padding:30px 32px;">
           <p style="margin:0 0 8px;color:#0f172a;font-size:17px;font-weight:700;">Hola {$admin_html} 👋</p>
           <p style="margin:0 0 16px;color:#475569;font-size:14px;line-height:1.6;">Se registraron <strong>{$cantidad} incidente" . ($cantidad > 1 ? 's' : '') . "</strong> {$ventana_html}.</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;margin-bottom:20px;">
            <thead>
              <tr style="background:#f8fafc;">
                <th style="padding:8px 10px;color:#64748b;font-size:11px;font-weight:700;text-align:left;border-bottom:1px solid #e2e8f0;">ID</th>
                <th style="padding:8px 10px;color:#64748b;font-size:11px;font-weight:700;text-align:left;border-bottom:1px solid #e2e8f0;">Tipo</th>
                <th style="padding:8px 10px;color:#64748b;font-size:11px;font-weight:700;text-align:left;border-bottom:1px solid #e2e8f0;">Dirección</th>
                <th style="padding:8px 10px;color:#64748b;font-size:11px;font-weight:700;text-align:left;border-bottom:1px solid #e2e8f0;">Barrio</th>
                <th style="padding:8px 10px;color:#64748b;font-size:11px;font-weight:700;text-align:left;border-bottom:1px solid #e2e8f0;">Gravedad</th>
              </tr>
            </thead>
            <tbody>
              {$filas_html}
            </tbody>
          </table>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="{$url}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 34px;border-radius:10px;">Ver todas las incidencias</a>
            </td></tr>
          </table>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #e2e8f0;">
          <p style="margin:0;color:#94a3b8;font-size:11px;line-height:1.6;">Mensaje automático del Sistema de Reporte Ciudadano — echo3dlaser.com.ar. No respondas este email.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    
    $limite = md5(uniqid((string)microtime(), true));
    $headers = implode("\r\n", [
        'From: SRCI <admin@echo3dlaser.com.ar>',
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"{$limite}\"",
    ]);
    
    $cuerpo = "--{$limite}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texto)) . "\r\n"
            . "--{$limite}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$limite}--\r\n";
    
    return @mail($admin['email'], '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $headers, '-fadmin@echo3dlaser.com.ar');
}
?>
