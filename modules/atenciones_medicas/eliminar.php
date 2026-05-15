<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
if ($id) {
    // Verificar si está siendo usado en reportes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes WHERE atencion_medica_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        // Desactivar en lugar de eliminar
        $stmt = $pdo->prepare("UPDATE atenciones_medicas SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM atenciones_medicas WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: listar.php?msg=deleted');
exit;