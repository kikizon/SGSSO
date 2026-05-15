<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

if (!isset($usuario_id)) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$empleado_id = $_POST['empleado_id'] ?? 0;
$alergia_id = $_POST['alergia_id'] ?? 0;
$accion = $_POST['accion'] ?? '';

if (!$empleado_id || !$alergia_id || !in_array($accion, ['tiene', 'no_tiene'])) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    if ($accion === 'tiene') {
        $stmt = $pdo->prepare("INSERT INTO empleado_alergia (empleado_id, alergia_id) VALUES (?, ?)");
        $stmt->execute([$empleado_id, $alergia_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM empleado_alergia WHERE empleado_id = ? AND alergia_id = ?");
        $stmt->execute([$empleado_id, $alergia_id]);
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;