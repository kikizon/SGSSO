<?php
require_once '../../includes/auth.php';
exigir('sucursales.eliminar');

$id = $_GET['id'] ?? 0;
if ($id) {
    // Verificar si hay empleados asociados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE sucursal_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        // Desactivar en lugar de eliminar
        $stmt = $pdo->prepare("UPDATE sucursales SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM sucursales WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: listar.php?msg=deleted');
exit;