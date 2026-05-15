<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, numero_empleado, nombre 
                       FROM empleados 
                       WHERE activo = 1 
                         AND (numero_empleado LIKE ? OR nombre LIKE ?)
                       ORDER BY nombre
                       LIMIT 20");
$stmt->execute(["%$q%", "%$q%"]);
$empleados = $stmt->fetchAll();

echo json_encode($empleados);
exit;