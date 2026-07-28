<?php
// Purga automática de la papelera (>30 días). Ejecutar por cron:
//   php /ruta/al/proyecto/modules/papelera/cron_purga.php
// o por web con un token: cron_purga.php?token=TU_TOKEN_SECRETO (cámbialo abajo)
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/papelera.php';

$CRON_TOKEN = 'CAMBIA_ESTE_TOKEN';
$esCli = (php_sapi_name() === 'cli');
if (!$esCli && (($_GET['token'] ?? '') !== $CRON_TOKEN)) {
    http_response_code(403); exit('No autorizado.');
}
$n = papelera_purgar_expiradas($pdo, 30);
echo "Purgados: $n" . ($esCli ? "\n" : '');
