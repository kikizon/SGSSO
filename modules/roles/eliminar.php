<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
exigir('roles.gestionar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    header('Location: listar.php?err=' . urlencode('Solicitud inválida.'));
    exit;
}
$id = (int)($_POST['id'] ?? 0);

$st = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$st->execute([$id]);
$rol = $st->fetch();
if (!$rol) { header('Location: listar.php?err=' . urlencode('El rol no existe.')); exit; }
if ((int)$rol['es_sistema'] === 1) {
    header('Location: listar.php?err=' . urlencode('El rol de sistema no se puede eliminar.'));
    exit;
}
$c = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol_id = ?");
$c->execute([$id]);
if ((int)$c->fetchColumn() > 0) {
    header('Location: listar.php?err=' . urlencode('No se puede eliminar: hay usuarios con ese rol.'));
    exit;
}
$pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
registrar_auditoria($pdo, $usuario_id, 'DELETE', 'roles', $id, json_encode(['nombre' => $rol['nombre']], JSON_UNESCAPED_UNICODE));
header('Location: listar.php?msg=' . urlencode('Rol eliminado.'));
exit;
