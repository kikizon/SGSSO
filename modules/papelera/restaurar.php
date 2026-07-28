<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/papelera.php';
exigir('papelera.restaurar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header('Location: listar.php?err=' . urlencode('Solicitud inválida.')); exit;
}
$id = (int)($_POST['id'] ?? 0);
[$ok, $motivo] = papelera_restaurar($pdo, $id, $usuario_id);
if ($ok) {
    registrar_auditoria($pdo, $usuario_id, 'RESTORE', 'papelera', $id, null);
    header('Location: listar.php?msg=' . urlencode('Elemento restaurado.'));
} else {
    header('Location: listar.php?err=' . urlencode($motivo));
}
exit;
