<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
if ($id) {
    // Verificar si el empleado tiene reportes asociados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes WHERE empleado_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    // Auditoria
    $stmt = $pdo->prepare("SELECT numero_empleado, nombre FROM empleados WHERE id = ?");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if ($emp) {
        registrar_auditoria($pdo, $usuario_id, 'DELETE', 'empleados', $id, 
            json_encode(['numero' => $emp['numero_empleado'], 'nombre' => $emp['nombre']]));
    }
    

    if ($count > 0) {
        // Desactivar en lugar de eliminar
        $stmt = $pdo->prepare("UPDATE empleados SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM empleados WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: listar.php?msg=deleted');
exit;