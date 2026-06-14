<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/image_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/auditoria6s/listar.php');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Token CSRF inválido.');
}

$es_admin = ($usuario_rol === 'admin');
$accion = $_POST['accion'] ?? '';

// ----------------- CREAR BORRADOR -----------------
if ($accion === 'crear') {
    $sucursal_id = (int)($_POST['sucursal_id'] ?? 0);
    $departamento_id = (int)($_POST['departamento_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');

    // Validar fecha
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) { $fecha = date('Y-m-d'); }
    // Alcance por sucursal
    if (!$es_admin) { $sucursal_id = (int)$usuario_sucursal_id; }
    if (!$sucursal_id || !$departamento_id) { redirect('modules/auditoria6s/realizar.php'); }

    $stmt = $pdo->prepare("INSERT INTO auditorias_6s (sucursal_id, departamento_id, fecha, auditor_id, estado)
                           VALUES (?, ?, ?, ?, 'borrador')");
    $stmt->execute([$sucursal_id, $departamento_id, $fecha, $usuario_id]);
    $nuevo = $pdo->lastInsertId();
    registrar_auditoria($pdo, $usuario_id, 'INSERT', 'auditorias_6s', $nuevo, json_encode([
        'sucursal_id' => $sucursal_id, 'departamento_id' => $departamento_id, 'fecha' => $fecha
    ]));
    redirect('modules/auditoria6s/realizar.php?id=' . $nuevo);
}

// ----------------- GUARDAR / FINALIZAR -----------------
$auditoria_id = (int)($_POST['auditoria_id'] ?? 0);
$finalizar = ($_POST['finalizar'] ?? '0') === '1';

$stmt = $pdo->prepare("SELECT * FROM auditorias_6s WHERE id = ?");
$stmt->execute([$auditoria_id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }
if (!$es_admin && $aud['sucursal_id'] != $usuario_sucursal_id) {
    redirect('modules/auditoria6s/listar.php');
}

// Criterios válidos para este departamento
$cstmt = $pdo->prepare("SELECT cr.id FROM criterios_6s cr
                        JOIN criterios_6s_departamento cd ON cd.criterio_id = cr.id AND cd.departamento_id = ?
                        WHERE cr.activo = 1");
$cstmt->execute([$aud['departamento_id']]);
$criterios_validos = array_map('intval', array_column($cstmt->fetchAll(), 'id'));
$total_criterios = count($criterios_validos);

// Empleados válidos como responsables (de la sucursal de la auditoría)
// Firmantes válidos = empleados del departamento auditado en esa sucursal
$emp = $pdo->prepare("SELECT id FROM empleados WHERE departamento_id = ? AND sucursal_id = ? AND activo = 1");
$emp->execute([$aud['departamento_id'], $aud['sucursal_id']]);
$firmantes_validos = array_flip(array_map('intval', array_column($emp->fetchAll(), 'id')));

// --- Validación de imágenes con MIME real (finfo) ---
$mime_ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$max_bytes = 8 * 1024 * 1024;
function guardar_foto_6s($tmp, $err, $size, $mime_ext, $max_bytes) {
    if ($err !== UPLOAD_ERR_OK) return null;
    if ($size <= 0 || $size > $max_bytes) return null;
    if (!is_uploaded_file($tmp)) return null;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if (!isset($mime_ext[$mime])) return null; // solo imágenes reales
    $nombre = uniqid('s6_', true) . '.' . $mime_ext[$mime];
    if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0755, true); }
    if (!is_writable(UPLOAD_DIR)) return null;
    if (!move_uploaded_file($tmp, UPLOAD_DIR . $nombre)) return null;
    comprimir_imagen(UPLOAD_DIR . $nombre); // redimensiona/recomprime en el lugar
    return $nombre;
}

$calif_in = $_POST['calif'] ?? [];
$prio_in  = $_POST['prioridad'] ?? [];
$dias_in  = $_POST['dias'] ?? [];
$com_in   = $_POST['coment'] ?? [];

$pdo->beginTransaction();
try {
    $suma_puntos = 0; // para evaluación total (no contestados = 0 al finalizar)
    $answered = 0;

    foreach ($criterios_validos as $cid) {
        $calif = isset($calif_in[$cid]) && $calif_in[$cid] !== '' ? (int)$calif_in[$cid] : null;
        if ($calif !== null && ($calif < 1 || $calif > 4)) $calif = null;
        if ($calif !== null) $answered++;
        $puntaje = $calif !== null ? $calif * 25 : null;
        if ($puntaje !== null) $suma_puntos += $puntaje;

        $prioridad = in_array($prio_in[$cid] ?? '', ['Urgente','Normal','No urgente'], true) ? $prio_in[$cid] : null;
        $dias = isset($dias_in[$cid]) && $dias_in[$cid] !== '' ? max(0, (int)$dias_in[$cid]) : null;
        $comentarios = isset($com_in[$cid]) ? clean_input($com_in[$cid]) : null;
        if ($comentarios === '') $comentarios = null;

        $fecha_compromiso = null;
        if ($dias !== null) {
            $fc = new DateTime($aud['fecha']);
            $fc->modify("+{$dias} day");
            $fecha_compromiso = $fc->format('Y-m-d');
        }

        // UPSERT respuesta (unique auditoria_id + criterio_id)
        $up = $pdo->prepare("INSERT INTO auditorias_6s_respuestas
            (auditoria_id, criterio_id, calificacion, puntaje, prioridad, dias_para_corregir, fecha_compromiso, comentarios)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE calificacion=VALUES(calificacion), puntaje=VALUES(puntaje),
                prioridad=VALUES(prioridad),
                dias_para_corregir=VALUES(dias_para_corregir), fecha_compromiso=VALUES(fecha_compromiso),
                comentarios=VALUES(comentarios)");
        $up->execute([$auditoria_id, $cid, $calif, $puntaje, $prioridad, $dias, $fecha_compromiso, $comentarios]);

        // id de la respuesta (para fotos)
        $rid = $pdo->prepare("SELECT id FROM auditorias_6s_respuestas WHERE auditoria_id = ? AND criterio_id = ?");
        $rid->execute([$auditoria_id, $cid]);
        $respuesta_id = (int)$rid->fetchColumn();

        // Fotos (campo foto{cid}[])
        $campo = 'foto' . $cid;
        if (!empty($_FILES[$campo]['name'][0])) {
            $n = count($_FILES[$campo]['name']);
            for ($i = 0; $i < $n; $i++) {
                $nombre = guardar_foto_6s(
                    $_FILES[$campo]['tmp_name'][$i],
                    $_FILES[$campo]['error'][$i],
                    $_FILES[$campo]['size'][$i],
                    $mime_ext, $max_bytes
                );
                if ($nombre) {
                    $pdo->prepare("INSERT INTO auditorias_6s_evidencias (respuesta_id, nombre_archivo, tipo) VALUES (?, ?, 'imagen')")
                        ->execute([$respuesta_id, $nombre]);
                }
            }
        }
    }

    // Firmantes de conformidad (reemplazar el conjunto)
    $pdo->prepare("DELETE FROM auditorias_6s_firmantes WHERE auditoria_id = ?")->execute([$auditoria_id]);
    $insf = $pdo->prepare("INSERT IGNORE INTO auditorias_6s_firmantes (auditoria_id, empleado_id) VALUES (?, ?)");
    foreach (($_POST['firmantes'] ?? []) as $eid) {
        $eid = (int)$eid;
        if (isset($firmantes_validos[$eid])) { $insf->execute([$auditoria_id, $eid]); }
    }

    // No se permite finalizar si faltan criterios por contestar
    $incompleto = false;
    if ($finalizar && $answered < $total_criterios) {
        $finalizar = false;
        $incompleto = true;
    }

    // Evaluación total
    if ($finalizar) {
        $eval_total = $total_criterios > 0 ? round($suma_puntos / $total_criterios, 2) : 0;
        $estado = 'finalizada';
    } else {
        // Borrador: promedio de los contestados (referencia rápida)
        $contestados = $pdo->prepare("SELECT COUNT(*) FROM auditorias_6s_respuestas WHERE auditoria_id = ? AND calificacion IS NOT NULL");
        $contestados->execute([$auditoria_id]);
        $nc = (int)$contestados->fetchColumn();
        $eval_total = $nc > 0 ? round($suma_puntos / $nc, 2) : null;
        $estado = 'borrador';
    }

    $pdo->prepare("UPDATE auditorias_6s SET estado = ?, evaluacion_total = ? WHERE id = ?")
        ->execute([$estado, $eval_total, $auditoria_id]);

    $pdo->commit();

    registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'auditorias_6s', $auditoria_id, json_encode([
        'estado' => $estado, 'evaluacion_total' => $eval_total
    ]));

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit('Error al guardar la auditoría.');
}

if (!empty($incompleto)) {
    $faltan = $total_criterios - $answered;
    redirect('modules/auditoria6s/realizar.php?id=' . $auditoria_id . '&err=incompleto&faltan=' . $faltan);
} elseif ($finalizar) {
    redirect('modules/auditoria6s/ver.php?id=' . $auditoria_id . '&msg=finalizada');
} else {
    redirect('modules/auditoria6s/realizar.php?id=' . $auditoria_id . '&msg=guardada');
}