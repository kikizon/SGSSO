<?php
/**
 * Borrado/desactivación en bloque de departamentos.
 * Regla: si el departamento tiene empleados u otras dependencias, se DESACTIVA
 * (soft-delete); si no, se ELIMINA. PRG + CSRF + auditoría por cada uno.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if ($usuario_rol !== 'admin') { redirect('modules/dashboard/'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/departamentos/listar.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token CSRF inválido.'); }

$ids = array_values(array_unique(array_map('intval', (array)($_POST['ids'] ?? []))));
$ids = array_filter($ids, fn($v) => $v > 0);

if (empty($ids)) {
    redirect('modules/departamentos/listar.php?err=' . urlencode('No seleccionaste departamentos.'));
}

$eliminados = 0; $desactivados = 0;

$cntEmp = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE departamento_id = ?");
$del    = $pdo->prepare("DELETE FROM departamentos WHERE id = ?");
$off    = $pdo->prepare("UPDATE departamentos SET activo = 0 WHERE id = ?");

foreach ($ids as $id) {
    // ¿Tiene empleados asociados? → desactivar
    $cntEmp->execute([$id]);
    if ((int)$cntEmp->fetchColumn() > 0) {
        $off->execute([$id]);
        registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'departamentos', $id,
            json_encode(['accion' => 'desactivado_bloque', 'motivo' => 'empleados_asociados'], JSON_UNESCAPED_UNICODE));
        $desactivados++;
        continue;
    }
    // Intentar eliminar; si hay otras dependencias (FK), caer a desactivar
    try {
        $del->execute([$id]);
        registrar_auditoria($pdo, $usuario_id, 'DELETE', 'departamentos', $id,
            json_encode(['accion' => 'eliminado_bloque'], JSON_UNESCAPED_UNICODE));
        $eliminados++;
    } catch (PDOException $e) {
        $off->execute([$id]);
        registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'departamentos', $id,
            json_encode(['accion' => 'desactivado_bloque', 'motivo' => 'en_uso'], JSON_UNESCAPED_UNICODE));
        $desactivados++;
    }
}

$msg = "Procesados: " . count($ids) . ". Eliminados: $eliminados · Desactivados (en uso): $desactivados.";
redirect('modules/departamentos/listar.php?msg=' . urlencode($msg));
