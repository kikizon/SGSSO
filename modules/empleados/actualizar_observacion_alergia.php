<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

if (!isset($usuario_id)) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$empleado_id = $_POST['empleado_id'] ?? 0;
$alergia_id = $_POST['alergia_id'] ?? 0;
$observaciones = trim($_POST['observaciones'] ?? '');

if (!$empleado_id || !$alergia_id) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE empleado_alergia SET observaciones = ? WHERE empleado_id = ? AND alergia_id = ?");
    $stmt->execute([$observaciones, $empleado_id, $alergia_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;