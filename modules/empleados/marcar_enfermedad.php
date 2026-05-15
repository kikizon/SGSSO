<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$empleado_id = $_POST['empleado_id'] ?? 0;
$enfermedad_id = $_POST['enfermedad_id'] ?? 0;
$accion = $_POST['accion'] ?? '';

if (!$empleado_id || !$enfermedad_id || !in_array($accion, ['tiene', 'no_tiene'])) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    if ($accion === 'tiene') {
        $stmt = $pdo->prepare("INSERT INTO empleado_enfermedad (empleado_id, enfermedad_id) VALUES (?, ?)");
        $stmt->execute([$empleado_id, $enfermedad_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM empleado_enfermedad WHERE empleado_id = ? AND enfermedad_id = ?");
        $stmt->execute([$empleado_id, $enfermedad_id]);
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;