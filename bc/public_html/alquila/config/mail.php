<?php
/**
 * Configuración de email SMTP (PHPMailer)
 * Ajustar según el servidor de correo del tenant o del sistema
 */

define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_ENCRYPTION', 'tls');   // 'tls' o 'ssl'
define('MAIL_USERNAME',   '');       // ← configurar en producción
define('MAIL_PASSWORD',   '');       // ← configurar en producción
define('MAIL_FROM',       '');       // email remitente por defecto
define('MAIL_FROM_NAME',  APP_NAME);
