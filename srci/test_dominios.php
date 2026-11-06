<?php
declare(strict_types=1);

// Prueba de entrega desde los distintos dominios alojados en el VPS.
// Todos usan el mismo mail() local -> mismo relay global.

$destino = 'ernestogn@gmail.com';

$dominios = [
  'echo3dlaser.com.ar'   => ['admin@echo3dlaser.com.ar', 'webmaster@echo3dlaser.com.ar'],
  'cadenaavicola.com'    => ['webmaster@cadenaavicola.com', 'prensa@cadenaavicola.com'],
  'catedraavicola.com.ar'=> ['webmaster@catedraavicola.com.ar'],
  'arteyoficios.com.ar'  => ['webmaster@arteyoficios.com.ar'],
];

echo "Destino de prueba: $destino\n";
echo "Fecha: " . date('Y-m-d H:i') . "\n\n";

$limite = md5(uniqid((string)microtime(), true));

foreach ($dominios as $dominio => $remitentes) {
  foreach ($remitentes as $desde) {
    $asunto = "PRUEBA de entrega desde {$desde} — SRCI/VPS";
    $texto  = "Prueba de envio desde el dominio {$dominio} ({$desde}).\n"
            . "Si recibis este mail, el dominio puede enviar correctamente.\n"
            . "Fecha: " . date('Y-m-d H:i:s') . "\n";

    $cuerpo = "--{$limite}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $texto . "\r\n"
            . "--{$limite}--\r\n";

    $headers = "From: {$desde}\r\n"
             . "Reply-To: {$desde}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: multipart/alternative; boundary=\"{$limite}\"\r\n";

    $ok = @mail($destino, $asunto, $cuerpo, $headers, "-f{$desde}");
    echo ($ok ? '  ACEPTADO  ' : '  RECHAZADO ') . "desde: {$desde}\n";
    usleep(300000);
  }
}

echo "\nEsperando 20s para que salgan...\n";
sleep(20);
