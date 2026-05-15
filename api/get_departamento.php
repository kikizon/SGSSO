<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isset($_GET['empleado_id']) || !is_numeric($_GET['empleado_id'])) {
    echo json_encode(['error' => 'ID de empleado inválido']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.id as departamento_id, d.nombre as departamento_nombre,
           e.sucursal_id, s.nombre as sucursal_nombre
    FROM empleados e
    JOIN departamentos d ON e.departamento_id = d.id
    JOIN sucursales s ON e.sucursal_id = s.id
    WHERE e.id = ?
");
$stmt->execute([$_GET['empleado_id']]);
$data = $stmt->fetch();

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'Empleado no encontrado']);
}
?>