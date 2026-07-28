<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/papelera.php';
exigir('papelera.purgar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header('Location: listar.php?err=' . urlencode('Solicitud inválida.')); exit;
}
$n = papelera_vaciar($pdo);
registrar_auditoria($pdo, $usuario_id, 'EMPTY', 'papelera', 0, json_encode(['purgados' => $n]));
header('Location: listar.php?msg=' . urlencode("Papelera vaciada ($n elemento(s))."));
exit;
