<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$es_admin = ($usuario_rol === 'admin');
if (!$es_admin && $usuario_rol !== 'supervisor') {
    redirect('modules/auditoria6s/listar.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { redirect('modules/auditoria6s/listar.php'); }

$stmt = $pdo->prepare("SELECT * FROM auditorias_6s WHERE id = ?");
$stmt->execute([$id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }
// Alcance por sucursal para supervisor
if (!$es_admin && $aud['sucursal_id'] != $usuario_sucursal_id) {
    redirect('modules/auditoria6s/listar.php');
}

// Borrar archivos físicos de evidencias
$ev = $pdo->prepare("SELECT e.nombre_archivo
                     FROM auditorias_6s_evidencias e
                     JOIN auditorias_6s_respuestas r ON r.id = e.respuesta_id
                     WHERE r.auditoria_id = ?");
$ev->execute([$id]);
foreach ($ev->fetchAll() as $f) {
    $ruta = UPLOAD_DIR . $f['nombre_archivo'];
    if (is_file($ruta)) { @unlink($ruta); }
}

// Auditoría del sistema antes de borrar
registrar_auditoria($pdo, $usuario_id, 'DELETE', 'auditorias_6s', $id, json_encode([
    'sucursal_id' => $aud['sucursal_id'], 'departamento_id' => $aud['departamento_id'], 'fecha' => $aud['fecha']
]));

// Borra cabecera (respuestas y evidencias caen por ON DELETE CASCADE)
$pdo->prepare("DELETE FROM auditorias_6s WHERE id = ?")->execute([$id]);

redirect('modules/auditoria6s/listar.php?msg=deleted');