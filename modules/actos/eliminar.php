<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
if ($id) {
    // Si tiene reportes asociados, mejor desactivar
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes WHERE acto_inseguro_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $stmt = $pdo->prepare("UPDATE actos_inseguros SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM actos_inseguros WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: listar.php?msg=deleted');
exit;