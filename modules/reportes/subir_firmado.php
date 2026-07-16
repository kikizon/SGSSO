<?php
/**
 * Sube uno o varios documentos firmados/escaneados de un reporte.
 * Valida MIME real con finfo. Comprime las imágenes. PRG + CSRF.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/image_optim.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/reportes/listar.php');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$reporte_id = (int) ($_POST['reporte_id'] ?? 0);
if (!$reporte_id) { redirect('modules/reportes/listar.php'); }

$st = $pdo->prepare("SELECT id, sucursal_id FROM reportes WHERE id = ?");
$st->execute([$reporte_id]);
$rep = $st->fetch();
if (!$rep) { redirect('modules/reportes/listar.php'); }
if ($usuario_rol !== 'admin' && isset($usuario_sucursal_id) && $rep['sucursal_id'] != $usuario_sucursal_id) {
    redirect('modules/reportes/listar.php');
}

$volver = 'modules/reportes/ver_reporte.php?id=' . $reporte_id;

if (empty($_FILES['firmados']['name'][0])) {
    redirect($volver . '&err=' . urlencode('No seleccionaste ningún archivo.'));
}

$mimePermitidos = [
    'application/pdf' => 'documento',
    'image/jpeg'      => 'imagen',
    'image/png'       => 'imagen',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);

if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0755, true); }

$subidos = 0; $errores = [];
$total = count($_FILES['firmados']['name']);
for ($i = 0; $i < $total; $i++) {
    $nombreOrig = $_FILES['firmados']['name'][$i];
    if ($_FILES['firmados']['error'][$i] !== UPLOAD_ERR_OK) { $errores[] = $nombreOrig; continue; }
    if ($_FILES['firmados']['size'][$i] > 15 * 1024 * 1024) { $errores[] = "$nombreOrig (>15MB)"; continue; }

    $mime = $finfo->file($_FILES['firmados']['tmp_name'][$i]);
    if (!isset($mimePermitidos[$mime])) { $errores[] = "$nombreOrig (tipo no permitido)"; continue; }

    $tipo = $mimePermitidos[$mime];
    $ext = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg');
    $nombreArchivo = 'firmado_' . $reporte_id . '_' . uniqid() . '.' . $ext;
    $destino = UPLOAD_DIR . $nombreArchivo;

    if (move_uploaded_file($_FILES['firmados']['tmp_name'][$i], $destino)) {
        if ($tipo === 'imagen') { optimizar_imagen($destino); } // comprime fotos escaneadas
        $ins = $pdo->prepare("INSERT INTO reportes_firmados (reporte_id, nombre_archivo, nombre_original, tipo, subido_por)
                              VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$reporte_id, $nombreArchivo, mb_substr($nombreOrig, 0, 255), $tipo, $usuario_id]);
        $subidos++;
    } else {
        $errores[] = "$nombreOrig (no se pudo guardar)";
    }
}

if ($subidos > 0) {
    registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'reportes', $reporte_id,
        json_encode(['firmados_subidos' => $subidos], JSON_UNESCAPED_UNICODE));
}

$qs = 'msg=' . urlencode("Documentos subidos: $subidos.");
if ($errores) { $qs .= '&err=' . urlencode('No se subieron: ' . implode(', ', $errores)); }
redirect($volver . '&' . $qs);
