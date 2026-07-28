<?php
require_once '../../includes/auth.php';
exigir('tipos_accidente.eliminar');

$id = $_GET['id'] ?? 0;
if ($id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reportes WHERE accidente_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    if ($count > 0) {
        $stmt = $pdo->prepare("UPDATE tipos_accidente SET activo = 0 WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM tipos_accidente WHERE id = ?");
        $stmt->execute([$id]);
    }
}
header('Location: listar.php?msg=deleted');
exit;