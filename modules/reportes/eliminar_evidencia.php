<?php
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no válido']);
    exit;
}

// Obtener nombre del archivo para eliminarlo del disco
$stmt = $pdo->prepare("SELECT nombre_archivo FROM reportes_evidencias WHERE id = ?");
$stmt->execute([$id]);
$archivo = $stmt->fetchColumn();

if ($archivo && file_exists(UPLOAD_DIR . $archivo)) {
    unlink(UPLOAD_DIR . $archivo);
}

$stmt = $pdo->prepare("DELETE FROM reportes_evidencias WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true]);
exit;