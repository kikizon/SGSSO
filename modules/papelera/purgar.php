<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/papelera.php';
exigir('papelera.purgar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header('Location: listar.php?err=' . urlencode('Solicitud inválida.')); exit;
}
$id = (int)($_POST['id'] ?? 0);
if (papelera_purgar($pdo, $id)) {
    registrar_auditoria($pdo, $usuario_id, 'PURGE', 'papelera', $id, null);
    header('Location: listar.php?msg=' . urlencode('Elemento eliminado definitivamente.'));
} else {
    header('Location: listar.php?err=' . urlencode('No se pudo eliminar.'));
}
exit;
