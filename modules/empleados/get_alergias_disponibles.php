<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$empleado_id = $_GET['empleado_id'] ?? 0;
if (!$empleado_id) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, nombre FROM alergias 
        WHERE activo = 1 
          AND id NOT IN (SELECT alergia_id FROM empleado_alergia WHERE empleado_id = ?)
        ORDER BY nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute([$empleado_id]);
echo json_encode($stmt->fetchAll());
exit;