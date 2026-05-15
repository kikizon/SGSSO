<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
if ($id && $id != $usuario_id) 
{
    // Verificar si tiene reportes creados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes WHERE reportado_por = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    // Antes de eliminar/desactivar
    $stmt = $pdo->prepare("SELECT nombre_completo, email FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usr = $stmt->fetch();
    if ($usr) {
        registrar_auditoria($pdo, $usuario_id, 'DELETE', 'usuarios', $id, 
            json_encode(['nombre' => $usr['nombre_completo'], 'email' => $usr['email']])
        );
    }

    if ($count > 0) {
        // No eliminar, solo desactivar
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: listar.php?msg=deleted');
exit;