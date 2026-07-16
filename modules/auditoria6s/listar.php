<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_semanas.php';

function color_6s($p) {
    if ($p === null || $p === '') return 'secondary';
    if ($p >= 85) return 'success';
    if ($p >= 70) return 'info';
    if ($p >= 50) return 'warning';
    return 'danger';
}

$es_admin = ($usuario_rol === 'admin');
[$anioAct, $semAct] = s6_semana_actual();

// --- Filtros ---
$f_sucursal = $_GET['sucursal_id'] ?? '';
$f_estado   = $_GET['estado'] ?? '';
$modo       = $_GET['modo'] ?? 'todas';   // todas | semana | rango
if (!in_array($modo, ['todas', 'semana', 'rango'], true)) $modo = 'todas';

$anio_d = (int)($_GET['anio_d'] ?? $anioAct);
$sem_d  = (int)($_GET['sem_d'] ?? $semAct);
$anio_h = (int)($_GET['anio_h'] ?? $anioAct);
$sem_h  = (int)($_GET['sem_h'] ?? $semAct);

$where = []; $params = [];
if (!$es_admin) { $where[] = 'a.sucursal_id = ?'; $params[] = $usuario_sucursal_id; }
elseif ($f_sucursal !== '') { $where[] = 'a.sucursal_id = ?'; $params[] = (int)$f_sucursal; }
if ($f_estado !== '') { $where[] = 'a.estado = ?'; $params[] = $f_estado; }

$rangoTexto = '';
if ($modo === 'semana') {
    $where[] = 'a.fecha BETWEEN ? AND ?';
    $params[] = s6_lunes($anio_d, $sem_d); $params[] = s6_domingo($anio_d, $sem_d);
    $rangoTexto = s6_label_semana($anio_d, $sem_d);
} elseif ($modo === 'rango') {
    $iniA = s6_lunes($anio_d, $sem_d); $finA = s6_domingo($anio_h, $sem_h);
    // Asegurar orden
    if ($finA < $iniA) { [$iniA, $finA] = [s6_lunes($anio_h, $sem_h), s6_domingo($anio_d, $sem_d)]; }
    $where[] = 'a.fecha BETWEEN ? AND ?';
    $params[] = $iniA; $params[] = $finA;
    $rangoTexto = s6_label_semana($anio_d, $sem_d) . '  →  ' . s6_label_semana($anio_h, $sem_h);
}
$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

$per_page = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$cnt = $pdo->prepare("SELECT COUNT(*) FROM auditorias_6s a $where_sql");
$cnt->execute($params);
$total_rows = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$sql = "SELECT a.*, s.nombre AS sucursal, u.nombre_completo AS auditor,
               (SELECT COUNT(*) FROM auditorias_6s_departamentos ad WHERE ad.auditoria_id = a.id) AS n_areas
        FROM auditorias_6s a
        JOIN sucursales s ON s.id = a.sucursal_id
        LEFT JOIN usuarios u ON u.id = a.auditor_id
        $where_sql ORDER BY a.fecha DESC, a.id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$auditorias = $stmt->fetchAll();

$qs_filtros = http_build_query(array_filter([
    'sucursal_id'=>$f_sucursal,'estado'=>$f_estado,'modo'=>$modo,
    'anio_d'=>$anio_d,'sem_d'=>$sem_d,'anio_h'=>$anio_h,'sem_h'=>$sem_h,
], fn($v) => $v !== '' && $v !== null));

if ($es_admin) {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
} else {
    $sucursales = $pdo->prepare("SELECT id, nombre FROM sucursales WHERE id = ?");
    $sucursales->execute([$usuario_sucursal_id]); $sucursales = $sucursales->fetchAll();
}

// Helper para options de semana (1..53)
function opciones_semana($sel) {
    $h = '';
    for ($w = 1; $w <= 53; $w++) { $h .= '<option value="'.$w.'" '.($w===$sel?'selected':'').'>Semana '.$w.'</option>'; }
    return $h;
}
function opciones_anio($sel, $act) {
    $h = '';
    for ($y = $act + 1; $y >= $act - 3; $y--) { $h .= '<option value="'.$y.'" '.($y===$sel?'selected':'').'>'.$y.'</option>'; }
    return $h;
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-clipboard-check"></i> Auditorías 6S</h2>

<?php if (($_GET['msg'] ?? '') === 'deleted'): ?><div class="alert alert-success">Auditoría eliminada.</div>
<?php elseif ($_GET['msg'] ?? false): ?><div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
<?php if ($_GET['err'] ?? false): ?><div class="alert alert-warning"><?= htmlspecialchars($_GET['err']) ?></div><?php endif; ?>

<div class="row mb-3 align-items-center">
    <div class="col-md-7">
        <a href="realizar.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva auditoría</a>
        <a href="detalle_semana.php" class="btn btn-outline-secondary"><i class="fas fa-calendar-week"></i> Detalle semanal</a>
        <a href="tendencias.php" class="btn btn-outline-info"><i class="fas fa-chart-line"></i> Tendencias</a>
        <a href="resumen.php" class="btn btn-outline-dark"><i class="fas fa-clipboard-list"></i> Resumen</a>
    </div>
    <div class="col-md-5 text-end"><span class="text-muted">Total: <?= $total_rows ?></span></div>
</div>

<form method="get" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <?php if ($es_admin): ?>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Sucursal</label>
            <select name="sucursal_id" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($sucursales as $s): ?><option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="borrador"   <?= $f_estado === 'borrador'   ? 'selected' : '' ?>>Borrador</option>
                <option value="finalizada" <?= $f_estado === 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Periodo</label>
            <select name="modo" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="todas"  <?= $modo === 'todas'  ? 'selected' : '' ?>>Todas</option>
                <option value="semana" <?= $modo === 'semana' ? 'selected' : '' ?>>Una semana</option>
                <option value="rango"  <?= $modo === 'rango'  ? 'selected' : '' ?>>Entre semanas</option>
            </select>
        </div>
        <?php if ($modo === 'semana' || $modo === 'rango'): ?>
        <div class="col-6 col-md-1"><label class="form-label small mb-1">Año</label><select name="anio_d" class="form-select form-select-sm"><?= opciones_anio($anio_d, $anioAct) ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1"><?= $modo === 'rango' ? 'Desde' : 'Semana' ?></label><select name="sem_d" class="form-select form-select-sm"><?= opciones_semana($sem_d) ?></select></div>
        <?php endif; ?>
        <?php if ($modo === 'rango'): ?>
        <div class="col-6 col-md-1"><label class="form-label small mb-1">Año</label><select name="anio_h" class="form-select form-select-sm"><?= opciones_anio($anio_h, $anioAct) ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Hasta</label><select name="sem_h" class="form-select form-select-sm"><?= opciones_semana($sem_h) ?></select></div>
        <?php endif; ?>
        <div class="col-12 d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-secondary"><i class="fas fa-filter"></i> Filtrar</button>
            <a href="listar.php" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            <?php if ($rangoTexto): ?><span class="align-self-center text-muted small">Mostrando: <?= htmlspecialchars($rangoTexto) ?></span><?php endif; ?>
        </div>
    </div>
</form>

<?php if (empty($auditorias)): ?>
    <div class="alert alert-info">No hay auditorías con esos filtros.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th>Semana</th><th>Sucursal</th><th class="text-center">Áreas</th><th>Auditor</th><th class="text-center">Estado</th><th class="text-center">Evaluación</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
            <?php foreach ($auditorias as $a): ?>
            <tr>
                <td><?= ($a['anio'] && $a['semana']) ? htmlspecialchars(s6_label_semana((int)$a['anio'], (int)$a['semana'])) : format_date_es($a['fecha']) ?></td>
                <td><?= htmlspecialchars($a['sucursal']) ?></td>
                <td class="text-center"><span class="badge bg-secondary"><?= (int)$a['n_areas'] ?></span></td>
                <td><?= htmlspecialchars($a['auditor'] ?? '—') ?></td>
                <td class="text-center"><?php if ($a['estado'] === 'finalizada'): ?><span class="badge bg-success">Finalizada</span><?php else: ?><span class="badge bg-warning text-dark">Borrador</span><?php endif; ?></td>
                <td class="text-center"><?php if ($a['evaluacion_total'] !== null): ?><span class="badge bg-<?= color_6s($a['evaluacion_total']) ?>"><?= number_format($a['evaluacion_total'], 1) ?>%</span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                <td class="text-end">
                    <a href="ver.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver"><i class="fas fa-eye"></i></a>
                    <a href="realizar.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <?php if ($es_admin || $usuario_rol === 'supervisor'): ?>
                    <a href="eliminar.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Eliminar esta auditoría y sus evidencias? Esta acción no se puede deshacer.');"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<nav><ul class="pagination pagination-sm justify-content-center">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= $qs_filtros ? $qs_filtros.'&' : '' ?>page=<?= $page - 1 ?>">Anterior</a></li>
    <li class="page-item disabled"><span class="page-link">Página <?= $page ?> de <?= $total_pages ?></span></li>
    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?<?= $qs_filtros ? $qs_filtros.'&' : '' ?>page=<?= $page + 1 ?>">Siguiente</a></li>
</ul></nav>
<?php endif; ?>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
