<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_inc.php';

// Acceso: admin y supervisor
if ($usuario_rol !== 'admin' && $usuario_rol !== 'supervisor') {
    redirect('modules/dashboard/');
}
$es_admin = ($usuario_rol === 'admin');

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Filtros
$f_estado = $_GET['estado'] ?? 'todos';
if (!in_array($f_estado, ['abierto', 'cerrado', 'todos'], true)) $f_estado = 'abierto';

$where = ["r.tipo = 'accidente'"];
$params = [];
if (!$es_admin) { $where[] = "r.sucursal_id IN ($usuario_sucursales_sql)"; }
elseif (($_GET['sucursal_id'] ?? '') !== '') { $where[] = 'r.sucursal_id = ?'; $params[] = (int)$_GET['sucursal_id']; }

// Solo accidentes con incapacidad (tramos o días registrados)
$where[] = "(r.dias_perdidos > 0 OR EXISTS (SELECT 1 FROM incapacidad_tramos it WHERE it.reporte_id = r.id))";

if ($f_estado === 'abierto') $where[] = 'r.fecha_regreso IS NULL';
elseif ($f_estado === 'cerrado') $where[] = 'r.fecha_regreso IS NOT NULL';

$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT r.id, r.fecha, r.dias_perdidos, r.fecha_regreso,
               e.numero_empleado, e.nombre AS empleado, s.nombre AS sucursal,
               (SELECT COUNT(*) FROM incapacidad_tramos it WHERE it.reporte_id = r.id) AS n_tramos
        FROM reportes r
        JOIN empleados e ON e.id = r.empleado_id
        JOIN sucursales s ON s.id = r.sucursal_id
        $where_sql
        ORDER BY (r.fecha_regreso IS NULL) DESC, r.fecha DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll();

$sucursales = $es_admin
    ? $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll()
    : [];

include '../../includes/header.php';
?>

<h2><i class="fas fa-user-injured"></i> Seguimiento de incapacidades</h2>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-warning"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<form method="get" class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <?php if ($es_admin): ?>
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1">Sucursal</label>
      <select name="sucursal_id" class="form-select form-select-sm">
        <option value="">Todas</option>
        <?php foreach ($sucursales as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($_GET['sucursal_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1">Estado</label>
      <select name="estado" class="form-select form-select-sm">
        <option value="abierto" <?= $f_estado === 'abierto' ? 'selected' : '' ?>>En seguimiento</option>
        <option value="cerrado" <?= $f_estado === 'cerrado' ? 'selected' : '' ?>>Cerrados (con regreso)</option>
        <option value="todos"   <?= $f_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
      </select>
    </div>
    <div class="col-auto"><button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrar</button></div>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Accidente</th><th>Empleado</th><th>Sucursal</th>
        <th class="text-center">Días incap.</th><th class="text-center">Tramos</th>
        <th class="text-center">Estado</th><th>Fecha de regreso</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($filas)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Sin accidentes con incapacidad en este filtro.</td></tr>
      <?php else: foreach ($filas as $f):
        $estado = $f['fecha_regreso'] ? 'cerrado' : 'abierto'; ?>
        <tr>
          <td>#<?= (int)$f['id'] ?> · <?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
          <td><?= htmlspecialchars($f['numero_empleado'] . ' - ' . $f['empleado']) ?></td>
          <td><?= htmlspecialchars($f['sucursal']) ?></td>
          <td class="text-center"><strong><?= (int)$f['dias_perdidos'] ?></strong></td>
          <td class="text-center"><?= (int)$f['n_tramos'] ?></td>
          <td class="text-center">
            <?php if ($estado === 'cerrado'): ?>
              <span class="badge bg-secondary">Cerrado</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark">En seguimiento</span>
            <?php endif; ?>
          </td>
          <td><?= $f['fecha_regreso'] ? date('d/m/Y', strtotime($f['fecha_regreso'])) : '—' ?></td>
          <td class="text-end"><a href="seguimiento.php?reporte_id=<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-stethoscope"></i> Seguimiento</a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include '../../includes/footer.php'; ?>
