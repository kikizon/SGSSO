<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/image_helper.php';
require_once __DIR__ . '/_semanas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/auditoria6s/listar.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token CSRF inválido.'); }

$es_admin = ($usuario_rol === 'admin');
$accion = $_POST['accion'] ?? '';

// ----------------- CREAR BORRADOR (sucursal + semana) -----------------
if ($accion === 'crear') {
    $sucursal_id = (int)($_POST['sucursal_id'] ?? 0);
    $anio = (int)($_POST['anio'] ?? 0);
    $semana = (int)($_POST['semana'] ?? 0);
    if (!$es_admin && !in_array((int)$sucursal_id, $usuario_sucursales, true)) { $sucursal_id = (int)($usuario_sucursales[0] ?? 0); }

    [$anioAct, ] = s6_semana_actual();
    if ($anio < 2020 || $anio > $anioAct + 1) $anio = $anioAct;
    if ($semana < 1 || $semana > s6_semanas_en_anio($anio)) { redirect('modules/auditoria6s/realizar.php'); }
    if (!$sucursal_id) { redirect('modules/auditoria6s/realizar.php'); }

    $lunes = s6_lunes($anio, $semana); // fecha de referencia

    $stmt = $pdo->prepare("INSERT INTO auditorias_6s (sucursal_id, departamento_id, fecha, anio, semana, fecha_inicio, auditor_id, estado)
                           VALUES (?, NULL, ?, ?, ?, NOW(), ?, 'borrador')");
    $stmt->execute([$sucursal_id, $lunes, $anio, $semana, $usuario_id]);
    $nuevo = $pdo->lastInsertId();
    registrar_auditoria($pdo, $usuario_id, 'INSERT', 'auditorias_6s', $nuevo,
        json_encode(['sucursal_id' => $sucursal_id, 'anio' => $anio, 'semana' => $semana], JSON_UNESCAPED_UNICODE));
    redirect('modules/auditoria6s/realizar.php?id=' . $nuevo);
}

// ----------------- GUARDAR / FINALIZAR -----------------
$auditoria_id = (int)($_POST['auditoria_id'] ?? 0);
$finalizar = ($_POST['finalizar'] ?? '0') === '1';

$stmt = $pdo->prepare("SELECT * FROM auditorias_6s WHERE id = ?");
$stmt->execute([$auditoria_id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }
if (!$es_admin && !in_array((int)$aud['sucursal_id'], $usuario_sucursales, true)) { redirect('modules/auditoria6s/listar.php'); }

$depRows = $pdo->query("SELECT DISTINCT cd.departamento_id
                        FROM criterios_6s_departamento cd
                        JOIN criterios_6s cr ON cr.id = cd.criterio_id AND cr.activo = 1
                        JOIN departamentos d ON d.id = cd.departamento_id AND d.activo = 1")->fetchAll();
$departamentos = array_map('intval', array_column($depRows, 'departamento_id'));

$critPorDep = [];
$critStmt = $pdo->prepare("SELECT cr.id FROM criterios_6s cr
                           JOIN criterios_6s_departamento cd ON cd.criterio_id = cr.id AND cd.departamento_id = ?
                           WHERE cr.activo = 1");
foreach ($departamentos as $dep) {
    $critStmt->execute([$dep]);
    $critPorDep[$dep] = array_map('intval', array_column($critStmt->fetchAll(), 'id'));
}

$mime_ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$max_bytes = 8 * 1024 * 1024;
function guardar_foto_6s($tmp, $err, $size, $mime_ext, $max_bytes) {
    if ($err !== UPLOAD_ERR_OK) return null;
    if ($size <= 0 || $size > $max_bytes) return null;
    if (!is_uploaded_file($tmp)) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset($mime_ext[$mime])) return null;
    $nombre = uniqid('s6_', true) . '.' . $mime_ext[$mime];
    if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0755, true); }
    if (!is_writable(UPLOAD_DIR)) return null;
    if (!move_uploaded_file($tmp, UPLOAD_DIR . $nombre)) return null;
    comprimir_imagen(UPLOAD_DIR . $nombre);
    return $nombre;
}

$calif_in = $_POST['calif'] ?? [];
$prio_in  = $_POST['prioridad'] ?? [];
$dias_in  = $_POST['dias'] ?? [];
$com_in   = $_POST['coment'] ?? [];
$firm_in  = $_POST['firmantes'] ?? [];

$pdo->beginTransaction();
try {
    $total_criterios = 0;   // total de criterios (todas las áreas)
    $resueltos_total = 0;   // criterios resueltos (con calificación O marcados N.A.)
    $dep_scores = [];

    foreach ($departamentos as $dep) {
        $crits = $critPorDep[$dep];
        $total_dep = count($crits);
        $total_criterios += $total_dep;
        $suma_dep = 0;   // suma de puntajes (solo calificados)
        $ans_dep  = 0;   // criterios calificados (1..4)
        $na_dep   = 0;   // criterios marcados N.A.

        foreach ($crits as $cid) {
            $raw = $calif_in[$dep][$cid] ?? '';
            $na  = ($raw === 'na');
            $calif = (!$na && $raw !== '') ? (int)$raw : null;
            if ($calif !== null && ($calif < 1 || $calif > 4)) $calif = null;

            if ($na) {
                $na_dep++; $resueltos_total++;
            } elseif ($calif !== null) {
                $ans_dep++; $resueltos_total++;
            }

            $puntaje = $calif !== null ? $calif * 25 : null;
            if ($puntaje !== null) $suma_dep += $puntaje;

            $prioridad = in_array($prio_in[$dep][$cid] ?? '', ['Urgente','Normal','No urgente'], true) ? $prio_in[$dep][$cid] : null;
            $dias = isset($dias_in[$dep][$cid]) && $dias_in[$dep][$cid] !== '' ? max(0, (int)$dias_in[$dep][$cid]) : null;
            $comentarios = isset($com_in[$dep][$cid]) ? clean_input($com_in[$dep][$cid]) : null;
            if ($comentarios === '') $comentarios = null;

            $fecha_compromiso = null;
            if ($dias !== null) { $fc = new DateTime($aud['fecha']); $fc->modify("+{$dias} day"); $fecha_compromiso = $fc->format('Y-m-d'); }

            $up = $pdo->prepare("INSERT INTO auditorias_6s_respuestas
                (auditoria_id, departamento_id, criterio_id, calificacion, no_aplica, puntaje, prioridad, dias_para_corregir, fecha_compromiso, comentarios)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE calificacion=VALUES(calificacion), no_aplica=VALUES(no_aplica), puntaje=VALUES(puntaje),
                    prioridad=VALUES(prioridad), dias_para_corregir=VALUES(dias_para_corregir),
                    fecha_compromiso=VALUES(fecha_compromiso), comentarios=VALUES(comentarios)");
            $up->execute([$auditoria_id, $dep, $cid, $calif, $na ? 1 : 0, $puntaje, $prioridad, $dias, $fecha_compromiso, $comentarios]);

            $rid = $pdo->prepare("SELECT id FROM auditorias_6s_respuestas WHERE auditoria_id = ? AND departamento_id = ? AND criterio_id = ?");
            $rid->execute([$auditoria_id, $dep, $cid]);
            $respuesta_id = (int)$rid->fetchColumn();

            $campo = 'foto_' . $dep . '_' . $cid;
            if (!empty($_FILES[$campo]['name'][0])) {
                $n = count($_FILES[$campo]['name']);
                for ($i = 0; $i < $n; $i++) {
                    $nombre = guardar_foto_6s($_FILES[$campo]['tmp_name'][$i], $_FILES[$campo]['error'][$i], $_FILES[$campo]['size'][$i], $mime_ext, $max_bytes);
                    if ($nombre) {
                        $pdo->prepare("INSERT INTO auditorias_6s_evidencias (respuesta_id, nombre_archivo, tipo) VALUES (?, ?, 'imagen')")
                            ->execute([$respuesta_id, $nombre]);
                    }
                }
            }
        }

        // Puntaje del departamento: los N.A. se EXCLUYEN del denominador.
        $efectivo = $total_dep - $na_dep; // criterios que sí puntúan
        if ($efectivo > 0) {
            $dep_scores[$dep] = $finalizar
                ? round($suma_dep / $efectivo, 2)
                : ($ans_dep > 0 ? round($suma_dep / $ans_dep, 2) : null);
        } else {
            // Todo el departamento es N.A.: no aporta puntaje
            $dep_scores[$dep] = null;
        }
    }

    $pdo->prepare("DELETE FROM auditorias_6s_firmantes WHERE auditoria_id = ?")->execute([$auditoria_id]);
    $insf = $pdo->prepare("INSERT IGNORE INTO auditorias_6s_firmantes (auditoria_id, departamento_id, empleado_id) VALUES (?, ?, ?)");
    $empChk = $pdo->prepare("SELECT 1 FROM empleados WHERE id = ? AND departamento_id = ? AND sucursal_id = ? AND activo = 1");
    foreach ($firm_in as $dep => $emps) {
        $dep = (int)$dep;
        foreach ((array)$emps as $eid) {
            $eid = (int)$eid;
            $empChk->execute([$eid, $dep, $aud['sucursal_id']]);
            if ($empChk->fetchColumn()) { $insf->execute([$auditoria_id, $dep, $eid]); }
        }
    }

    // Para finalizar, todos los criterios deben estar resueltos (calificados o N.A.)
    $incompleto = false;
    if ($finalizar && $resueltos_total < $total_criterios) { $finalizar = false; $incompleto = true; }

    $updDep = $pdo->prepare("INSERT INTO auditorias_6s_departamentos (auditoria_id, departamento_id, evaluacion_total)
                             VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE evaluacion_total = VALUES(evaluacion_total)");
    foreach ($dep_scores as $dep => $sc) { $updDep->execute([$auditoria_id, $dep, $sc]); }

    $valid = array_filter($dep_scores, fn($v) => $v !== null);
    $eval_total = count($valid) > 0 ? round(array_sum($valid) / count($valid), 2) : null;
    $estado = $finalizar ? 'finalizada' : 'borrador';

    if ($finalizar) {
        $pdo->prepare("UPDATE auditorias_6s SET estado = ?, evaluacion_total = ?, fecha_fin = NOW() WHERE id = ?")
            ->execute([$estado, $eval_total, $auditoria_id]);
    } else {
        $pdo->prepare("UPDATE auditorias_6s SET estado = ?, evaluacion_total = ? WHERE id = ?")
            ->execute([$estado, $eval_total, $auditoria_id]);
    }

    $pdo->commit();
    registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'auditorias_6s', $auditoria_id,
        json_encode(['estado' => $estado, 'evaluacion_total' => $eval_total], JSON_UNESCAPED_UNICODE));

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit('Error al guardar la auditoría.');
}

if (!empty($incompleto)) {
    $faltan = $total_criterios - $resueltos_total;
    redirect('modules/auditoria6s/realizar.php?id=' . $auditoria_id . '&err=incompleto&faltan=' . $faltan);
} elseif ($finalizar) {
    redirect('modules/auditoria6s/ver.php?id=' . $auditoria_id . '&msg=finalizada');
} else {
    redirect('modules/auditoria6s/realizar.php?id=' . $auditoria_id . '&msg=guardada');
}
