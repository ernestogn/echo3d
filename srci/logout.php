<?php
require_once __DIR__ . '/includes/auth.php';
iniciar_sesion();
$_SESSION = [];
session_destroy();
header('Location: /srci/login.php');
exit;
