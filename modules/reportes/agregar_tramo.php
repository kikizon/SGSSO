<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/image_optim.php';
require_once __DIR__ . '/_inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/incapacidades/listar.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token CSRF inválido.'); }
if ($usuario_rol !== 'admin' && $usuario_rol !== 'supervisor') { redirect('modules/dashboard/'); }

$reporte_id = (int) ($_POST['reporte_id'] ?? 0);
$rep = cargar_reporte_incapacidad($pdo, $reporte_id, $usuario_rol, $usuario_sucursales);
$volver = 'modules/incapacidades/seguimiento.php?reporte_id=' . $reporte_id;

if (!empty($rep['fecha_regreso'])) {
    redirect($volver . '&err=' . urlencode('El seguimiento está cerrado. Reábrelo para agregar tramos.'));
}

$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$dias  = (int) ($_POST['dias'] ?? 0);
$emisor = in_array($_POST['emisor'] ?? '', ['IMSS','particular','otro'], true) ? $_POST['emisor'] : 'IMSS';
$folio = trim($_POST['folio'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || $dias < 1) {
    redirect($volver . '&err=' . urlencode('Fecha o días no válidos.'));
}

if (empty($_FILES['documento']['name']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
    redirect($volver . '&err=' . urlencode('Debes adjuntar el documento del tramo.'));
}
if ($_FILES['documento']['size'] > 15 * 1024 * 1024) {
    redirect($volver . '&err=' . urlencode('El documento supera 15 MB.'));
}
$mimeMap = ['application/pdf' => ['documento','pdf'], 'image/jpeg' => ['imagen','jpg'], 'image/png' => ['imagen','png']];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['documento']['tmp_name']);
if (!isset($mimeMap[$mime])) {
    redirect($volver . '&err=' . urlencode('Documento no permitido (usa PDF, JPG o PNG).'));
}
[$tipoArchivo, $ext] = $mimeMap[$mime];

if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0755, true); }
$nombreArchivo = 'incap_' . $reporte_id . '_' . uniqid() . '.' . $ext;
$rutaDestino = UPLOAD_DIR . $nombreArchivo;
if (!move_uploaded_file($_FILES['documento']['tmp_name'], $rutaDestino)) {
    redirect($volver . '&err=' . urlencode('No se pudo guardar el documento.'));
}
if ($tipoArchivo === 'imagen') { optimizar_imagen($rutaDestino); } // comprime fotos del ST7/receta

$ins = $pdo->prepare("INSERT INTO incapacidad_tramos
        (reporte_id, fecha_inicio, dias, emisor, folio, nombre_archivo, nombre_original, tipo_archivo, creado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$ins->execute([$reporte_id, $fecha_inicio, $dias, $emisor, ($folio ?: null),
    $nombreArchivo, mb_substr($_FILES['documento']['name'], 0, 255), $tipoArchivo, $usuario_id]);

$total = recompute_dias_incapacidad($pdo, $reporte_id);

registrar_auditoria($pdo, $usuario_id, 'INSERT', 'incapacidad_tramos', (int)$pdo->lastInsertId(),
    json_encode(['reporte_id' => $reporte_id, 'dias' => $dias, 'total' => $total], JSON_UNESCAPED_UNICODE));

redirect($volver . '&msg=' . urlencode("Tramo registrado. Total de días: $total."));
