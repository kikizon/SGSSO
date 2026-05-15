<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$empleado_id = $_POST['empleado_id'] ?? 0;
$enfermedad_id = $_POST['enfermedad_id'] ?? 0;
$observaciones = trim($_POST['observaciones'] ?? '');

if (!$empleado_id || !$enfermedad_id) {
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE empleado_enfermedad SET observaciones = ? WHERE empleado_id = ? AND enfermedad_id = ?");
    $stmt->execute([$observaciones, $empleado_id, $enfermedad_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;