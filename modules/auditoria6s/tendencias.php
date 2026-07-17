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

// Granularidad de la evolución
$gran = in_array($_GET['gran'] ?? '', ['semana','mes','anio'], true) ? $_GET['gran'] : 'semana';

// Rango de semanas (por defecto: últimas 12 semanas)
[$anioDef, $semDef] = s6_semana_actual(date('Y-m-d', strtotime('-12 weeks')));
$anio_d = (int)($_GET['anio_d'] ?? $anioDef);
$sem_d  = (int)($_GET['sem_d'] ?? $semDef);
$anio_h = (int)($_GET['anio_h'] ?? $anioAct);
$sem_h  = (int)($_GET['sem_h'] ?? $semAct);
$f_desde = s6_lunes($anio_d, $sem_d);
$f_hasta = s6_domingo($anio_h, $sem_h);
if ($f_hasta < $f_desde) { [$f_desde, $f_hasta] = [s6_lunes($anio_h, $sem_h), s6_domingo($anio_d, $sem_d)]; }

$f_sucursal = $_GET['sucursal_id'] ?? '';
$f_depto    = $_GET['departamento_id'] ?? '';

// WHERE base (nivel auditoría)
$where = ["a.estado = 'finalizada'", 'a.fecha BETWEEN ? AND ?'];
$params = [$f_desde, $f_hasta];
if (!$es_admin) { $where[] = "a.sucursal_id IN ($usuario_sucursales_sql)"; }
elseif ($f_sucursal !== '') { $where[] = 'a.sucursal_id = ?'; $params[] = (int)$f_sucursal; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$depFiltro = ($f_depto !== '') ? (int)$f_depto : 0;

// 1) Evolución
$grp = $gran === 'semana' ? 'YEARWEEK(a.fecha,3)' : ($gran === 'anio' ? 'YEAR(a.fecha)' : "DATE_FORMAT(a.fecha,'%Y-%m')");
if ($depFiltro) {
    $sql = "SELECT $grp AS g, MIN(a.fecha) AS inicio, ROUND(AVG(ad.evaluacion_total),1) AS prom, COUNT(*) AS n
            FROM auditorias_6s a JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id AND ad.departamento_id = ?
            $where_sql GROUP BY g ORDER BY MIN(a.fecha)";
    $st = $pdo->prepare($sql); $st->execute(array_merge([$depFiltro], $params));
} else {
    $sql = "SELECT $grp AS g, MIN(a.fecha) AS inicio, ROUND(AVG(a.evaluacion_total),1) AS prom, COUNT(*) AS n
            FROM auditorias_6s a $where_sql GROUP BY g ORDER BY MIN(a.fecha)";
    $st = $pdo->prepare($sql); $st->execute($params);
}
$evol = $st->fetchAll();

// 2) Promedio por categoría (radar)
$catWhere = $where_sql . ($depFiltro ? ' AND r.departamento_id = ?' : '');
$catParams = $depFiltro ? array_merge($params, [$depFiltro]) : $params;
$sql = "SELECT cat.nombre, ROUND(AVG(COALESCE(r.puntaje,0)),1) AS prom
        FROM auditorias_6s a
        JOIN auditorias_6s_respuestas r ON r.auditoria_id = a.id
        JOIN criterios_6s cr ON cr.id = r.criterio_id
        JOIN categorias_6s cat ON cat.id = cr.categoria_id
        $catWhere GROUP BY cat.id ORDER BY cat.orden";
$st = $pdo->prepare($sql); $st->execute($catParams);
$por_cat = $st->fetchAll();

// 3) Promedio por departamento (desde tabla de departamentos de la auditoría)
$sql = "SELECT d.nombre, ROUND(AVG(ad.evaluacion_total),1) AS prom, COUNT(*) AS n
        FROM auditorias_6s a
        JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id
        JOIN departamentos d ON d.id = ad.departamento_id
        $where_sql " . ($depFiltro ? ' AND ad.departamento_id = ?' : '') . "
        GROUP BY d.id ORDER BY d.nombre";
$st = $pdo->prepare($sql); $st->execute($depFiltro ? array_merge($params, [$depFiltro]) : $params);
$por_depto = $st->fetchAll();
$rank = $por_depto; usort($rank, fn($a, $b) => ($b['prom'] <=> $a['prom']));

// 4) Promedio por sucursal (admin sin filtro)
$por_suc = [];
if ($es_admin && $f_sucursal === '') {
    $sql = "SELECT s.nombre, ROUND(AVG(a.evaluacion_total),1) AS prom, COUNT(*) AS n
            FROM auditorias_6s a JOIN sucursales s ON s.id = a.sucursal_id
            $where_sql GROUP BY s.id ORDER BY prom DESC";
    $st = $pdo->prepare($sql); $st->execute($params);
    $por_suc = $st->fetchAll();
}

// KPIs
if ($depFiltro) {
    $sql = "SELECT COUNT(*) AS total, ROUND(AVG(ad.evaluacion_total),1) AS prom
            FROM auditorias_6s a JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id AND ad.departamento_id = ? $where_sql";
    $st = $pdo->prepare($sql); $st->execute(array_merge([$depFiltro], $params));
} else {
    $sql = "SELECT COUNT(*) AS total, ROUND(AVG(a.evaluacion_total),1) AS prom FROM auditorias_6s a $where_sql";
    $st = $pdo->prepare($sql); $st->execute($params);
}
$kpi = $st->fetch();

// Catálogos
if ($es_admin) {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
} else {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 AND id IN ($usuario_sucursales_sql) ORDER BY nombre")->fetchAll();
}
$departamentos = $pdo->query("SELECT DISTINCT d.id, d.nombre FROM departamentos d
                              JOIN criterios_6s_departamento cd ON cd.departamento_id = d.id
                              JOIN criterios_6s cr ON cr.id = cd.criterio_id AND cr.activo = 1
                              WHERE d.activo = 1 ORDER BY d.nombre")->fetchAll();

// Etiquetas de evolución
$meses = ['', 'Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$lbl_evo = []; $dat_evo = [];
foreach ($evol as $r) {
    $t = strtotime($r['inicio']);
    if ($gran === 'semana') $lbl_evo[] = 'Sem ' . date('W', $t);
    elseif ($gran === 'anio') $lbl_evo[] = date('Y', $t);
    else $lbl_evo[] = $meses[(int)date('n', $t)] . ' ' . date('Y', $t);
    $dat_evo[] = $r['prom'] !== null ? (float)$r['prom'] : 0;
}
$titulo_evo = ['semana' => 'semanal', 'mes' => 'mensual', 'anio' => 'anual'][$gran];
$lbl_cat = array_map(fn($r) => $r['nombre'], $por_cat);
$dat_cat = array_map(fn($r) => (float)$r['prom'], $por_cat);
$lbl_dep = array_map(fn($r) => $r['nombre'], $por_depto);
$dat_dep = array_map(fn($r) => $r['prom'] !== null ? (float)$r['prom'] : 0, $por_depto);

function tw_opt_sem($sel) { $h=''; for($w=1;$w<=53;$w++){$h.='<option value="'.$w.'" '.($w===$sel?'selected':'').'>Sem '.$w.'</option>';} return $h; }
function tw_opt_anio($sel,$act){ $h=''; for($y=$act+1;$y>=$act-3;$y--){$h.='<option value="'.$y.'" '.($y===$sel?'selected':'').'>'.$y.'</option>';} return $h; }

include '../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h2 class="mb-0"><i class="fas fa-chart-line"></i> Tendencias 6S</h2>
  <a href="listar.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> Ver auditorías</a>
</div>
<p class="text-muted small">Solo auditorías <strong>finalizadas</strong>. Criterios sin contestar = 0. Mostrando de <strong><?= htmlspecialchars(s6_label_semana($anio_d,$sem_d)) ?></strong> a <strong><?= htmlspecialchars(s6_label_semana($anio_h,$sem_h)) ?></strong>.</p>

<form method="get" class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <?php if ($es_admin): ?>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Sucursal</label>
      <select name="sucursal_id" class="form-select form-select-sm"><option value="">Todas</option>
        <?php foreach ($sucursales as $s): ?><option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Departamento</label>
      <select name="departamento_id" class="form-select form-select-sm"><option value="">Todos</option>
        <?php foreach ($departamentos as $d): ?><option value="<?= $d['id'] ?>" <?= $f_depto == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Agrupar por</label>
      <select name="gran" class="form-select form-select-sm">
        <option value="semana" <?= $gran === 'semana' ? 'selected' : '' ?>>Semana</option>
        <option value="mes"    <?= $gran === 'mes'    ? 'selected' : '' ?>>Mes</option>
        <option value="anio"   <?= $gran === 'anio'   ? 'selected' : '' ?>>Año</option>
      </select></div>
    <div class="col-3 col-md-1"><label class="form-label small mb-1">Año</label><select name="anio_d" class="form-select form-select-sm"><?= tw_opt_anio($anio_d,$anioAct) ?></select></div>
    <div class="col-3 col-md-1"><label class="form-label small mb-1">Desde</label><select name="sem_d" class="form-select form-select-sm"><?= tw_opt_sem($sem_d) ?></select></div>
    <div class="col-3 col-md-1"><label class="form-label small mb-1">Año</label><select name="anio_h" class="form-select form-select-sm"><?= tw_opt_anio($anio_h,$anioAct) ?></select></div>
    <div class="col-3 col-md-1"><label class="form-label small mb-1">Hasta</label><select name="sem_h" class="form-select form-select-sm"><?= tw_opt_sem($sem_h) ?></select></div>
    <div class="col-12 col-md-1 d-flex align-items-end"><button class="btn btn-sm btn-secondary w-100"><i class="fas fa-filter"></i></button></div>
  </div>
</form>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body p-2"><div class="small text-muted">Auditorías</div><div class="h4 mb-0"><?= (int)$kpi['total'] ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body p-2"><div class="small text-muted">Promedio</div><div class="h4 mb-0"><span class="badge bg-<?= color_6s($kpi['prom']) ?>"><?= $kpi['prom'] !== null ? $kpi['prom'].'%' : '—' ?></span></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body p-2"><div class="small text-muted">Mejor área</div><div class="fw-bold text-truncate"><?= !empty($rank) ? htmlspecialchars($rank[0]['nombre']) : '—' ?></div><div class="small"><?= !empty($rank) ? $rank[0]['prom'].'%' : '' ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card text-center"><div class="card-body p-2"><div class="small text-muted">Menor área</div><div class="fw-bold text-truncate"><?= !empty($rank) ? htmlspecialchars(end($rank)['nombre']) : '—' ?></div><div class="small"><?= !empty($rank) ? end($rank)['prom'].'%' : '' ?></div></div></div></div>
</div>

<?php if ((int)$kpi['total'] === 0): ?>
  <div class="alert alert-info">No hay auditorías finalizadas en este período.</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-12"><div class="card"><div class="card-header">Evolución <?= $titulo_evo ?> (Evaluación)</div><div class="card-body"><canvas id="chEvo" height="90"></canvas></div></div></div>
  <div class="col-12 col-lg-6"><div class="card"><div class="card-header">Promedio por categoría 6S</div><div class="card-body"><canvas id="chCat" height="240"></canvas></div></div></div>
  <div class="col-12 col-lg-6"><div class="card"><div class="card-header">Promedio por departamento</div><div class="card-body"><canvas id="chDep" height="240"></canvas></div></div></div>
  <div class="col-12 col-lg-6"><div class="card"><div class="card-header">Detalle por departamento</div><div class="card-body p-0">
    <table class="table table-sm mb-0"><thead><tr><th>Departamento</th><th class="text-center">Eval.</th><th class="text-center">Promedio</th></tr></thead><tbody>
      <?php foreach ($rank as $d): ?><tr><td><?= htmlspecialchars($d['nombre']) ?></td><td class="text-center"><?= (int)$d['n'] ?></td><td class="text-center"><span class="badge bg-<?= color_6s($d['prom']) ?>"><?= $d['prom'] ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
  <?php if (!empty($por_suc)): ?>
  <div class="col-12 col-lg-6"><div class="card"><div class="card-header">Promedio por sucursal</div><div class="card-body p-0">
    <table class="table table-sm mb-0"><thead><tr><th>Sucursal</th><th class="text-center">Auditorías</th><th class="text-center">Promedio</th></tr></thead><tbody>
      <?php foreach ($por_suc as $s): ?><tr><td><?= htmlspecialchars($s['nombre']) ?></td><td class="text-center"><?= (int)$s['n'] ?></td><td class="text-center"><span class="badge bg-<?= color_6s($s['prom']) ?>"><?= $s['prom'] ?>%</span></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const radarOpts = { scales: { r: { min: 0, max: 100, ticks: { stepSize: 20 } } }, plugins: { legend: { display: false } } };
new Chart(document.getElementById('chEvo'), { type: 'line',
  data: { labels: <?= json_encode($lbl_evo) ?>, datasets: [{ label: 'Evaluación %', data: <?= json_encode($dat_evo) ?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.1)', fill: true, tension: .3, pointRadius: 4 }] },
  options: { scales: { y: { min: 0, max: 100 } }, plugins: { legend: { display: false } } } });
new Chart(document.getElementById('chCat'), { type: 'radar',
  data: { labels: <?= json_encode($lbl_cat) ?>, datasets: [{ data: <?= json_encode($dat_cat) ?>, backgroundColor: 'rgba(13,110,253,.2)', borderColor: '#0d6efd', pointBackgroundColor: '#0d6efd' }] }, options: radarOpts });
new Chart(document.getElementById('chDep'), { type: 'radar',
  data: { labels: <?= json_encode($lbl_dep) ?>, datasets: [{ data: <?= json_encode($dat_dep) ?>, backgroundColor: 'rgba(25,135,84,.2)', borderColor: '#198754', pointBackgroundColor: '#198754' }] }, options: radarOpts });
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
