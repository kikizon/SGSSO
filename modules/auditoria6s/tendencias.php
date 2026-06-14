<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

function color_6s($p) {
    if ($p === null || $p === '') return 'secondary';
    if ($p >= 85) return 'success';
    if ($p >= 70) return 'info';
    if ($p >= 50) return 'warning';
    return 'danger';
}

$es_admin = ($usuario_rol === 'admin');

// Granularidad de la evolución
$gran = in_array($_GET['gran'] ?? '', ['semana','mes','anio'], true) ? $_GET['gran'] : 'mes';
$def_desde = ['semana' => '-12 weeks', 'mes' => '-12 months', 'anio' => '-5 years'][$gran];

// Filtros
$f_sucursal = $_GET['sucursal_id'] ?? '';
$f_depto    = $_GET['departamento_id'] ?? '';
$f_desde    = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime($def_desde));
$f_hasta    = $_GET['fecha_hasta'] ?? date('Y-m-d');

// Solo auditorías finalizadas
$where  = ["a.estado = 'finalizada'"];
$params = [];
if (!$es_admin) {
    $where[] = 'a.sucursal_id = ?'; $params[] = $usuario_sucursal_id;
} elseif (!empty($f_sucursal)) {
    $where[] = 'a.sucursal_id = ?'; $params[] = $f_sucursal;
}
if (!empty($f_depto)) { $where[] = 'a.departamento_id = ?'; $params[] = $f_depto; }
if (!empty($f_desde)) { $where[] = 'a.fecha >= ?'; $params[] = $f_desde; }
if (!empty($f_hasta)) { $where[] = 'a.fecha <= ?'; $params[] = $f_hasta; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

// 1) Evolución según granularidad (grupo desde whitelist, seguro)
$grp = $gran === 'semana' ? 'YEARWEEK(a.fecha, 3)' : ($gran === 'anio' ? 'YEAR(a.fecha)' : "DATE_FORMAT(a.fecha, '%Y-%m')");
$sql = "SELECT $grp AS g, MIN(a.fecha) AS inicio, ROUND(AVG(a.evaluacion_total),1) AS prom, COUNT(*) AS n
        FROM auditorias_6s a $where_sql GROUP BY g ORDER BY MIN(a.fecha)";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$evol = $stmt->fetchAll();

// 2) Promedio por categoría (no contestados = 0)  -> radar
$sql = "SELECT cat.nombre, cat.orden, ROUND(AVG(COALESCE(r.puntaje,0)),1) AS prom
        FROM auditorias_6s a
        JOIN auditorias_6s_respuestas r ON r.auditoria_id = a.id
        JOIN criterios_6s cr ON cr.id = r.criterio_id
        JOIN categorias_6s cat ON cat.id = cr.categoria_id
        $where_sql GROUP BY cat.id ORDER BY cat.orden";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$por_cat = $stmt->fetchAll();

// 3) Promedio por departamento -> radar + tabla
$sql = "SELECT d.nombre, ROUND(AVG(a.evaluacion_total),1) AS prom, COUNT(*) AS n
        FROM auditorias_6s a JOIN departamentos d ON d.id = a.departamento_id
        $where_sql GROUP BY d.id ORDER BY d.nombre";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$por_depto = $stmt->fetchAll();
// Orden para "mejor/peor" (copia ordenada por promedio)
$rank = $por_depto;
usort($rank, fn($a, $b) => ($b['prom'] <=> $a['prom']));

// 4) Promedio por sucursal (solo admin sin filtro de sucursal)
$por_suc = [];
if ($es_admin && empty($f_sucursal)) {
    $sql = "SELECT s.nombre, ROUND(AVG(a.evaluacion_total),1) AS prom, COUNT(*) AS n
            FROM auditorias_6s a JOIN sucursales s ON s.id = a.sucursal_id
            $where_sql GROUP BY s.id ORDER BY prom DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $por_suc = $stmt->fetchAll();
}

// KPIs
$sql = "SELECT COUNT(*) AS total, ROUND(AVG(a.evaluacion_total),1) AS prom FROM auditorias_6s a $where_sql";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$kpi = $stmt->fetch();

// Catálogos para filtros
if ($es_admin) {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
} else {
    $sucursales = $pdo->prepare("SELECT id, nombre FROM sucursales WHERE id = ?");
    $sucursales->execute([$usuario_sucursal_id]); $sucursales = $sucursales->fetchAll();
}
$departamentos = $pdo->query("SELECT DISTINCT d.id, d.nombre
                              FROM departamentos d
                              JOIN criterios_6s_departamento cd ON cd.departamento_id = d.id
                              JOIN criterios_6s cr ON cr.id = cd.criterio_id AND cr.activo = 1
                              WHERE d.activo = 1 ORDER BY d.nombre")->fetchAll();

// Etiquetas de evolución según granularidad
$meses = ['', 'Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$lbl_evo = []; $dat_evo = [];
foreach ($evol as $r) {
    $t = strtotime($r['inicio']);
    if ($gran === 'semana')      $lbl_evo[] = 'Sem ' . date('d/m', $t);
    elseif ($gran === 'anio')    $lbl_evo[] = date('Y', $t);
    else                         $lbl_evo[] = $meses[(int)date('n', $t)] . ' ' . date('Y', $t);
    $dat_evo[] = $r['prom'] !== null ? (float)$r['prom'] : 0;
}
$titulo_evo = ['semana' => 'semanal', 'mes' => 'mensual', 'anio' => 'anual'][$gran];

$lbl_cat = array_map(fn($r) => $r['nombre'], $por_cat);
$dat_cat = array_map(fn($r) => (float)$r['prom'], $por_cat);
$lbl_dep = array_map(fn($r) => $r['nombre'], $por_depto);
$dat_dep = array_map(fn($r) => $r['prom'] !== null ? (float)$r['prom'] : 0, $por_depto);

include '../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h2 class="mb-0"><i class="fas fa-chart-line"></i> Tendencias 6S</h2>
  <a href="listar.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> Ver auditorías</a>
</div>
<p class="text-muted small">Solo se consideran auditorías <strong>finalizadas</strong>. Los criterios sin contestar cuentan como 0.</p>

<form method="get" class="card card-body mb-3">
  <div class="row g-2">
    <?php if ($es_admin): ?>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">Sucursal</label>
      <select name="sucursal_id" class="form-select form-select-sm">
        <option value="">Todas</option>
        <?php foreach ($sucursales as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">Departamento</label>
      <select name="departamento_id" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $f_depto == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">Agrupar por</label>
      <select name="gran" class="form-select form-select-sm">
        <option value="semana" <?= $gran === 'semana' ? 'selected' : '' ?>>Semana</option>
        <option value="mes"    <?= $gran === 'mes'    ? 'selected' : '' ?>>Mes</option>
        <option value="anio"   <?= $gran === 'anio'   ? 'selected' : '' ?>>Año</option>
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
    <div class="col-6 col-md-2 d-flex align-items-end">
      <button class="btn btn-sm btn-secondary w-100"><i class="fas fa-filter"></i> Aplicar</button>
    </div>
  </div>
</form>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body p-2">
      <div class="small text-muted">Auditorías</div>
      <div class="h4 mb-0"><?= (int)$kpi['total'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body p-2">
      <div class="small text-muted">Promedio global</div>
      <div class="h4 mb-0"><span class="badge bg-<?= color_6s($kpi['prom']) ?>"><?= $kpi['prom'] !== null ? $kpi['prom'].'%' : '—' ?></span></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body p-2">
      <div class="small text-muted">Mejor depto.</div>
      <div class="fw-bold text-truncate"><?= !empty($rank) ? htmlspecialchars($rank[0]['nombre']) : '—' ?></div>
      <div class="small"><?= !empty($rank) ? $rank[0]['prom'].'%' : '' ?></div>
    </div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center"><div class="card-body p-2">
      <div class="small text-muted">Menor depto.</div>
      <div class="fw-bold text-truncate"><?= !empty($rank) ? htmlspecialchars(end($rank)['nombre']) : '—' ?></div>
      <div class="small"><?= !empty($rank) ? end($rank)['prom'].'%' : '' ?></div>
    </div></div>
  </div>
</div>

<?php if ((int)$kpi['total'] === 0): ?>
  <div class="alert alert-info">No hay auditorías finalizadas en este período.</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-12">
    <div class="card"><div class="card-header">Evolución <?= $titulo_evo ?> (Evaluación Total)</div>
      <div class="card-body"><canvas id="chEvo" height="90"></canvas></div></div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card"><div class="card-header">Promedio por categoría 6S</div>
      <div class="card-body"><canvas id="chCat" height="240"></canvas></div></div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card"><div class="card-header">Promedio por departamento</div>
      <div class="card-body"><canvas id="chDep" height="240"></canvas></div></div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card"><div class="card-header">Detalle por departamento</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Departamento</th><th class="text-center">Auditorías</th><th class="text-center">Promedio</th></tr></thead>
          <tbody>
            <?php foreach ($rank as $d): ?>
              <tr><td><?= htmlspecialchars($d['nombre']) ?></td><td class="text-center"><?= (int)$d['n'] ?></td>
                  <td class="text-center"><span class="badge bg-<?= color_6s($d['prom']) ?>"><?= $d['prom'] ?>%</span></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
  </div>
  <?php if (!empty($por_suc)): ?>
  <div class="col-12 col-lg-6">
    <div class="card"><div class="card-header">Promedio por sucursal</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Sucursal</th><th class="text-center">Auditorías</th><th class="text-center">Promedio</th></tr></thead>
          <tbody>
            <?php foreach ($por_suc as $s): ?>
              <tr><td><?= htmlspecialchars($s['nombre']) ?></td><td class="text-center"><?= (int)$s['n'] ?></td>
                  <td class="text-center"><span class="badge bg-<?= color_6s($s['prom']) ?>"><?= $s['prom'] ?>%</span></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const radarOpts = { scales: { r: { min: 0, max: 100, ticks: { stepSize: 20 } } }, plugins: { legend: { display: false } } };

new Chart(document.getElementById('chEvo'), {
  type: 'line',
  data: { labels: <?= json_encode($lbl_evo) ?>,
    datasets: [{ label: 'Evaluación %', data: <?= json_encode($dat_evo) ?>, borderColor: '#0d6efd',
      backgroundColor: 'rgba(13,110,253,.1)', fill: true, tension: .3, pointRadius: 4 }] },
  options: { scales: { y: { min: 0, max: 100 } }, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('chCat'), {
  type: 'radar',
  data: { labels: <?= json_encode($lbl_cat) ?>,
    datasets: [{ label: 'Promedio %', data: <?= json_encode($dat_cat) ?>,
      backgroundColor: 'rgba(13,110,253,.2)', borderColor: '#0d6efd', pointBackgroundColor: '#0d6efd' }] },
  options: radarOpts
});

new Chart(document.getElementById('chDep'), {
  type: 'radar',
  data: { labels: <?= json_encode($lbl_dep) ?>,
    datasets: [{ label: 'Promedio %', data: <?= json_encode($dat_dep) ?>,
      backgroundColor: 'rgba(25,135,84,.2)', borderColor: '#198754', pointBackgroundColor: '#198754' }] },
  options: radarOpts
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>