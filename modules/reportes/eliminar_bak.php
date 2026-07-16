<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$id = $_GET['id'] ?? 0;
if ($id) {
    // Obtener datos del reporte antes de eliminar (para auditoría)
    $stmt = $pdo->prepare("SELECT tipo, empleado_id, fecha FROM reportes WHERE id = ?");
    $stmt->execute([$id]);
    $reporte = $stmt->fetch();

    if ($reporte) {
        // Registrar auditoría antes de eliminar
        $detalles = json_encode([
            'tipo'        => $reporte['tipo'],
            'empleado_id' => $reporte['empleado_id'],
            'fecha'       => $reporte['fecha']
        ]);
        registrar_auditoria($pdo, $usuario_id, 'DELETE', 'reportes', $id, $detalles);
    }

    // Eliminar archivos de evidencias
    $stmt = $pdo->prepare("SELECT nombre_archivo FROM reportes_evidencias WHERE reporte_id = ?");
    $stmt->execute([$id]);
    while ($archivo = $stmt->fetchColumn()) {
        $ruta = UPLOAD_DIR . $archivo;
        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }

    // Eliminar registros de evidencias de la base de datos
    $pdo->prepare("DELETE FROM reportes_evidencias WHERE reporte_id = ?")->execute([$id]);

    // Eliminar el reporte
    $stmt = $pdo->prepare("DELETE FROM reportes WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: listar.php?msg=deleted');
exit;