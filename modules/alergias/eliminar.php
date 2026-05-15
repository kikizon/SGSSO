<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
if ($id) {
    // Si tiene relaciones con empleados, desactivar en lugar de eliminar
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empleado_alergia WHERE alergia_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $stmt = $pdo->prepare("UPDATE alergias SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM alergias WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: listar.php?msg=deleted');
exit;