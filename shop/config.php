<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'echo_shop');
define('DB_USER', 'echo_admin');
define('DB_PASS', 'EchoRetro2@26');

define('SITE_NOMBRE', 'ECHO 3D LASER');
define('SITE_WHATSAPP', '5491155550000');
define('SITE_EMAIL', 'hola@echo3dlaser.com.ar');
define('SITE_CELULAR', '+54 9 11 5555-0000');

define('ADMIN_USER', 'echo');
define('ADMIN_PASS_HASH', '$2y$10$jmUj4O4vtLVCEsU1jdB7VeSUdjuqSeqyYJvn.NnQ/wrVjn.MIAm4C');

define('SITE_NOTIF_EMAIL', 'robot@echo3dlaser.com.ar');
define('SITE_MAIL_FROM', 'robot@echo3dlaser.com.ar');

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}