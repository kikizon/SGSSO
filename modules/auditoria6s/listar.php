<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Color del badge segun el puntaje (0-100)
function color_6s($p) {
    if ($p === null || $p === '') return 'secondary';
    if ($p >= 85) return 'success';
    if ($p >= 70) return 'info';
    if ($p >= 50) return 'warning';
    return 'danger';
}

$es_admin = ($usuario_rol === 'admin');

// Filtros
$f_sucursal = $_GET['sucursal_id'] ?? '';
$f_depto    = $_GET['departamento_id'] ?? '';
$f_desde    = $_GET['fecha_desde'] ?? '';
$f_hasta    = $_GET['fecha_hasta'] ?? '';
$f_estado   = $_GET['estado'] ?? '';

$where = [];
$params = [];

// Alcance por sucursal: no-admin solo ve su sucursal
if (!$es_admin) {
    $where[] = 'a.sucursal_id = ?';
    $params[] = $usuario_sucursal_id;
} elseif (!empty($f_sucursal)) {
    $where[] = 'a.sucursal_id = ?';
    $params[] = $f_sucursal;
}
if (!empty($f_depto))  { $where[] = 'a.departamento_id = ?'; $params[] = $f_depto; }
if (!empty($f_desde))  { $where[] = 'a.fecha >= ?';          $params[] = $f_desde; }
if (!empty($f_hasta))  { $where[] = 'a.fecha <= ?';          $params[] = $f_hasta; }
if ($f_estado !== '')  { $where[] = 'a.estado = ?';          $params[] = $f_estado; }

$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Paginación
$per_page = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$cnt = $pdo->prepare("SELECT COUNT(*) FROM auditorias_6s a $where_sql");
$cnt->execute($params);
$total_rows = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

$sql = "SELECT a.*, s.nombre AS sucursal, d.nombre AS departamento, u.nombre_completo AS auditor
        FROM auditorias_6s a
        JOIN sucursales s ON s.id = a.sucursal_id
        JOIN departamentos d ON d.id = a.departamento_id
        LEFT JOIN usuarios u ON u.id = a.auditor_id
        $where_sql
        ORDER BY a.fecha DESC, a.id DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$auditorias = $stmt->fetchAll();

// Query string para conservar filtros en la paginación
$qs_filtros = http_build_query(array_filter([
    'sucursal_id' => $f_sucursal, 'departamento_id' => $f_depto,
    'fecha_desde' => $f_desde, 'fecha_hasta' => $f_hasta, 'estado' => $f_estado,
], fn($v) => $v !== '' && $v !== null));

// Catalogos para filtros
if ($es_admin) {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
} else {
    $sucursales = $pdo->prepare("SELECT id, nombre FROM sucursales WHERE id = ?");
    $sucursales->execute([$usuario_sucursal_id]);
    $sucursales = $sucursales->fetchAll();
}
// Solo departamentos con criterios activos asignados
$departamentos = $pdo->query("SELECT DISTINCT d.id, d.nombre
                              FROM departamentos d
                              JOIN criterios_6s_departamento cd ON cd.departamento_id = d.id
                              JOIN criterios_6s cr ON cr.id = cd.criterio_id AND cr.activo = 1
                              WHERE d.activo = 1 ORDER BY d.nombre")->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-clipboard-check"></i> Auditorías 6S</h2>

<div class="row mb-3 align-items-center">
    <div class="col-md-6">
        <a href="realizar.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva auditoría</a>
        <a href="tendencias.php" class="btn btn-outline-info"><i class="fas fa-chart-line"></i> Tendencias</a>
        <a href="resumen.php" class="btn btn-outline-dark"><i class="fas fa-clipboard-list"></i> Resumen</a>
    </div>
    <div class="col-md-6 text-end">
        <span class="text-muted">Total: <?= $total_rows ?></span>
    </div>
</div>

<form method="get" class="card card-body mb-3">
    <div class="row g-2">
        <?php if ($es_admin): ?>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Sucursal</label>
            <select name="sucursal_id" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Departamento</label>
            <select name="departamento_id" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $f_depto == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($f_desde) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($f_hasta) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="borrador"   <?= $f_estado === 'borrador'   ? 'selected' : '' ?>>Borrador</option>
                <option value="finalizada" <?= $f_estado === 'finalizada' ? 'selected' : '' ?>>Finalizada</option>
            </select>
        </div>
        <div class="col-12 col-md-12 d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-secondary"><i class="fas fa-filter"></i> Filtrar</button>
            <a href="listar.php" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </div>
</form>

<?php if (empty($auditorias)): ?>
    <div class="alert alert-info">No hay auditorías con esos filtros.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Sucursal</th>
                <th>Departamento</th>
                <th>Auditor</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Evaluación</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($auditorias as $a): ?>
            <tr>
                <td><?= format_date_es($a['fecha']) ?></td>
                <td><?= htmlspecialchars($a['sucursal']) ?></td>
                <td><?= htmlspecialchars($a['departamento']) ?></td>
                <td><?= htmlspecialchars($a['auditor'] ?? '—') ?></td>
                <td class="text-center">
                    <?php if ($a['estado'] === 'finalizada'): ?>
                        <span class="badge bg-success">Finalizada</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Borrador</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($a['evaluacion_total'] !== null): ?>
                        <span class="badge bg-<?= color_6s($a['evaluacion_total']) ?>"><?= number_format($a['evaluacion_total'], 1) ?>%</span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="ver.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver"><i class="fas fa-eye"></i></a>
                    <a href="realizar.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                    <?php if ($es_admin || $usuario_rol === 'supervisor'): ?>
                    <a href="eliminar.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" title="Eliminar"
                       onclick="return confirm('¿Eliminar esta auditoría y sus evidencias? Esta acción no se puede deshacer.');"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($total_pages > 1): ?>
<nav>
  <ul class="pagination pagination-sm justify-content-center">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="?<?= $qs_filtros ? $qs_filtros.'&' : '' ?>page=<?= $page - 1 ?>">Anterior</a>
    </li>
    <li class="page-item disabled"><span class="page-link">Página <?= $page ?> de <?= $total_pages ?></span></li>
    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
      <a class="page-link" href="?<?= $qs_filtros ? $qs_filtros.'&' : '' ?>page=<?= $page + 1 ?>">Siguiente</a>
    </li>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>