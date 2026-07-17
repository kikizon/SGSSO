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

if ($es_admin) {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
} else {
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 AND id IN ($usuario_sucursales_sql) ORDER BY nombre")->fetchAll();
}
$f_sucursal = $es_admin ? ($_GET['sucursal_id'] ?? ($sucursales[0]['id'] ?? '')) : (in_array((int)($_GET['sucursal_id'] ?? 0), $usuario_sucursales, true) ? (int)$_GET['sucursal_id'] : ($usuario_sucursales[0] ?? ''));

// Rango de semanas (por defecto: semana actual)
$anio_d = (int)($_GET['anio_d'] ?? $anioAct); $sem_d = (int)($_GET['sem_d'] ?? $semAct);
$anio_h = (int)($_GET['anio_h'] ?? $anioAct); $sem_h = (int)($_GET['sem_h'] ?? $semAct);
$f_desde = s6_lunes($anio_d, $sem_d); $f_hasta = s6_domingo($anio_h, $sem_h);
if ($f_hasta < $f_desde) { [$f_desde, $f_hasta] = [s6_lunes($anio_h, $sem_h), s6_domingo($anio_d, $sem_d)]; }

$suc_nombre = '';
foreach ($sucursales as $s) { if ($s['id'] == $f_sucursal) $suc_nombre = $s['nombre']; }

$datos = $f_sucursal !== '' && $f_sucursal !== null;
$kpi = ['total' => 0, 'prom' => null]; $dep_scores = []; $fallas = []; $cat_dep = [];

if ($datos) {
    $base = [$f_sucursal, $f_desde, $f_hasta];

    $st = $pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(evaluacion_total),1) AS prom
                         FROM auditorias_6s WHERE estado='finalizada' AND sucursal_id=? AND fecha BETWEEN ? AND ?");
    $st->execute($base); $kpi = $st->fetch();

    $st = $pdo->prepare("SELECT d.id, d.nombre, ROUND(AVG(ad.evaluacion_total),1) AS prom, COUNT(*) AS n
                         FROM auditorias_6s a
                         JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id
                         JOIN departamentos d ON d.id = ad.departamento_id
                         WHERE a.estado='finalizada' AND a.sucursal_id=? AND a.fecha BETWEEN ? AND ?
                         GROUP BY d.id ORDER BY d.nombre");
    $st->execute($base); $dep_scores = $st->fetchAll();

    $st = $pdo->prepare("SELECT r.departamento_id AS dep_id, cat.nombre AS categoria, cat.orden,
                                ROUND(AVG(COALESCE(r.puntaje,0)),1) AS prom
                         FROM auditorias_6s a
                         JOIN auditorias_6s_respuestas r ON r.auditoria_id = a.id
                         JOIN criterios_6s cr ON cr.id = r.criterio_id
                         JOIN categorias_6s cat ON cat.id = cr.categoria_id
                         WHERE a.estado='finalizada' AND a.sucursal_id=? AND a.fecha BETWEEN ? AND ?
                         GROUP BY r.departamento_id, cat.id ORDER BY cat.orden");
    $st->execute($base);
    foreach ($st->fetchAll() as $row) {
        $cat_dep[$row['dep_id']]['labels'][] = $row['categoria'];
        $cat_dep[$row['dep_id']]['data'][]   = (float)$row['prom'];
    }

    $st = $pdo->prepare("SELECT r.departamento_id AS dep_id, cat.nombre AS categoria, cr.id AS crit_id, cr.texto,
                                r.puntaje, r.calificacion, r.comentarios, r.prioridad, r.fecha_compromiso,
                                a.fecha, u.nombre_completo AS auditor
                         FROM auditorias_6s a
                         JOIN auditorias_6s_respuestas r ON r.auditoria_id = a.id AND r.calificacion IS NOT NULL
                         JOIN criterios_6s cr ON cr.id = r.criterio_id
                         JOIN categorias_6s cat ON cat.id = cr.categoria_id
                         LEFT JOIN usuarios u ON u.id = a.auditor_id
                         WHERE a.estado='finalizada' AND a.sucursal_id=? AND a.fecha BETWEEN ? AND ?
                         ORDER BY cat.orden, cr.id, a.fecha DESC");
    $st->execute($base);
    $agg = [];
    foreach ($st->fetchAll() as $row) {
        $dep = $row['dep_id']; $cid = $row['crit_id'];
        if (!isset($agg[$dep][$cid])) {
            $agg[$dep][$cid] = ['categoria' => $row['categoria'], 'texto' => $row['texto'],
                                'evaluado' => 0, 'fallas' => 0, 'suma' => 0, 'detalles' => []];
        }
        $agg[$dep][$cid]['evaluado']++;
        $agg[$dep][$cid]['suma'] += (int)$row['puntaje'];
        if ((int)$row['puntaje'] < 100) {
            $agg[$dep][$cid]['fallas']++;
            $agg[$dep][$cid]['detalles'][] = ['fecha'=>$row['fecha'],'auditor'=>$row['auditor'],'puntaje'=>(int)$row['puntaje'],
                'calificacion'=>$row['calificacion'],'comentarios'=>$row['comentarios'],'prioridad'=>$row['prioridad'],'fecha_compromiso'=>$row['fecha_compromiso']];
        }
    }
    foreach ($agg as $dep => $crits) {
        $lista = [];
        foreach ($crits as $c) { if ($c['fallas'] > 0) { $c['prom'] = $c['evaluado'] ? round($c['suma']/$c['evaluado'],1) : 0; $lista[] = $c; } }
        usort($lista, fn($x, $y) => ($y['fallas'] <=> $x['fallas']) ?: ($x['prom'] <=> $y['prom']));
        $fallas[$dep] = $lista;
    }
}

$labels = [1 => 'No cumple y desconoce', 2 => 'No cumple', 3 => 'Cumple, falta mejorar', 4 => 'Sí cumple'];
$logoRel = 'assets/img/logo.png'; $logoExists = file_exists(__DIR__ . '/../../' . $logoRel);

function rs_opt_sem($sel) { $h=''; for($w=1;$w<=53;$w++){$h.='<option value="'.$w.'" '.($w===$sel?'selected':'').'>Sem '.$w.'</option>';} return $h; }
function rs_opt_anio($sel,$act){ $h=''; for($y=$act+1;$y>=$act-3;$y--){$h.='<option value="'.$y.'" '.($y===$sel?'selected':'').'>'.$y.'</option>';} return $h; }

include '../../includes/header.php';
?>
<style>
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .print-head { display: none; }
  @page { size: letter; margin: 12mm; }
  @media print {
    .navbar, .resumen-toolbar, footer, .screen-only { display: none !important; }
    body { margin: 0; } .card { break-inside: avoid; }
    .radar-box { max-width: 300px !important; margin: 0 auto; } .radar-box canvas { max-width: 100% !important; height: auto !important; }
    .print-head { display:flex; align-items:center; gap:16px; border-bottom:2px solid #0d6efd; padding-bottom:10px; margin-bottom:12px; }
    .print-head img { height:56px; } .print-head h1 { font-size:18px; margin:0; } .print-head .sub { color:#555; font-size:11px; }
  }
</style>

<div class="print-head">
  <?php if ($logoExists): ?><img src="<?= BASE_URL . $logoRel ?>" alt="Logo"><?php endif; ?>
  <div><h1>Hoja de Resumen 6S</h1><div class="sub">SUPERMM SYSO · <?= htmlspecialchars($suc_nombre ?: '') ?> · <?= format_date_es($f_desde) ?> — <?= format_date_es($f_hasta) ?></div></div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 screen-only">
  <h2 class="mb-0"><i class="fas fa-clipboard-list"></i> Hoja de resumen 6S</h2>
  <div class="resumen-toolbar d-flex gap-2">
    <button class="btn btn-sm btn-outline-dark" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
    <a href="listar.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> Auditorías</a>
  </div>
</div>
<p class="text-muted small screen-only">Solo auditorías <strong>finalizadas</strong>. Se listan los puntos donde el área <strong>no alcanzó 100%</strong>, por recurrencia.</p>

<form method="get" class="card card-body mb-3 resumen-toolbar">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-3"><label class="form-label small mb-1">Sucursal</label>
      <select name="sucursal_id" class="form-select form-select-sm" <?= $es_admin ? '' : 'disabled' ?>>
        <?php foreach ($sucursales as $s): ?><option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-3 col-md-1"><label class="form-label small mb-1">Año</label><select name="anio_d" class="form-select form-select-sm"><?= rs_opt_anio($anio_d,$anioAct) ?></select></div>
    <div class="col-3 col-md-2"><label class="form-label small mb-1">Desde</label><select name="sem_d" class="form-select form-select-sm"><?= rs_opt_sem($sem_d) ?></select></div>
    <div class="col-3 col-md-1"><label class="form-label small mb-1">Año</label><select name="anio_h" class="form-select form-select-sm"><?= rs_opt_anio($anio_h,$anioAct) ?></select></div>
    <div class="col-3 col-md-2"><label class="form-label small mb-1">Hasta</label><select name="sem_h" class="form-select form-select-sm"><?= rs_opt_sem($sem_h) ?></select></div>
    <div class="col-12 col-md-3 d-flex align-items-end"><button class="btn btn-sm btn-secondary w-100"><i class="fas fa-filter"></i> Aplicar</button></div>
  </div>
</form>

<div class="card mb-3"><div class="card-body"><div class="row align-items-center">
  <div class="col-md-4">
    <h4 class="mb-1"><?= htmlspecialchars($suc_nombre ?: '—') ?></h4>
    <div class="text-muted"><?= format_date_es($f_desde) ?> — <?= format_date_es($f_hasta) ?></div>
    <div class="row mt-3">
      <div class="col-6"><div class="small text-muted">Calificación</div><div class="h2 mb-0"><span class="badge bg-<?= color_6s($kpi['prom']) ?>"><?= $kpi['prom'] !== null ? $kpi['prom'].'%' : '—' ?></span></div></div>
      <div class="col-6"><div class="small text-muted">Auditorías</div><div class="h2 mb-0"><?= (int)$kpi['total'] ?></div></div>
    </div>
  </div>
  <div class="col-md-8"><?php if (!empty($dep_scores)): ?><div class="radar-box mx-auto" style="max-width:430px;"><canvas id="radarSucursal"></canvas></div><?php endif; ?></div>
</div></div></div>

<?php if (!$datos || (int)$kpi['total'] === 0): ?>
  <div class="alert alert-info">No hay auditorías finalizadas para esa sucursal y rango de semanas.</div>
<?php else: ?>
  <?php foreach ($dep_scores as $d): $items = $fallas[$d['id']] ?? []; ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><?= htmlspecialchars($d['nombre']) ?></strong>
      <span><span class="text-muted small me-2"><?= (int)$d['n'] ?> evaluación(es)</span><span class="badge bg-<?= color_6s($d['prom']) ?>"><?= $d['prom'] ?>%</span></span>
    </div>
    <div class="card-body p-0">
      <?php if (!empty($cat_dep[$d['id']])): ?>
      <div class="p-2 border-bottom d-flex justify-content-center"><div class="radar-box" style="max-width:340px;width:100%;"><canvas class="radarDepto" data-dep="<?= (int)$d['id'] ?>"></canvas></div></div>
      <?php endif; ?>
      <?php if (empty($items)): ?>
        <div class="p-3 text-success"><i class="fas fa-check-circle"></i> Sin observaciones: todos los puntos evaluados alcanzaron 100%.</div>
      <?php else: ?>
        <table class="table table-sm mb-0">
          <thead><tr><th>Categoría</th><th>Punto a mejorar</th><th class="text-center">Veces sin 100%</th><th class="text-center">Promedio</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td class="text-muted small align-top"><?= htmlspecialchars($it['categoria']) ?></td>
              <td><div class="fw-semibold"><?= htmlspecialchars($it['texto']) ?></div>
                <div class="small text-muted mt-1">
                  <?php foreach ($it['detalles'] as $de): ?>
                    <div class="mb-1"><i class="fas fa-circle" style="font-size:.4rem;vertical-align:middle;"></i>
                      <?= format_date_es($de['fecha']) ?> · <i class="fas fa-user-check"></i> <?= htmlspecialchars($de['auditor'] ?? 's/auditor') ?> ·
                      <span class="badge bg-<?= color_6s($de['puntaje']) ?>"><?= htmlspecialchars($labels[$de['calificacion']]) ?> (<?= $de['puntaje'] ?>)</span>
                      <?php if ($de['prioridad']): ?> <span class="badge bg-light text-dark border"><?= htmlspecialchars($de['prioridad']) ?></span><?php endif; ?>
                      <?php if ($de['fecha_compromiso']): ?> <span class="text-muted">· compromiso: <?= format_date_es($de['fecha_compromiso']) ?></span><?php endif; ?>
                      <?php if ($de['comentarios']): ?><div class="ms-3 fst-italic">“<?= htmlspecialchars($de['comentarios']) ?>”</div><?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </td>
              <td class="text-center align-top"><span class="badge bg-danger"><?= (int)$it['fallas'] ?></span> <span class="text-muted small">de <?= (int)$it['evaluado'] ?></span></td>
              <td class="text-center align-top"><span class="badge bg-<?= color_6s($it['prom']) ?>"><?= $it['prom'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const RADAR_DEP = { labels: <?= json_encode(array_map(fn($d) => $d['nombre'], $dep_scores)) ?>, data: <?= json_encode(array_map(fn($d) => $d['prom'] !== null ? (float)$d['prom'] : 0, $dep_scores)) ?> };
const RADAR_CAT = <?= json_encode($cat_dep) ?>;
const radarOpts = { responsive: true, maintainAspectRatio: true, animation: false, devicePixelRatio: 2,
  scales: { r: { min: 60, max: 100, ticks: { stepSize: 10, font: { size: 9 } }, pointLabels: { font: { size: 10 } } } }, plugins: { legend: { display: false } } };
if (document.getElementById('radarSucursal')) {
  new Chart(document.getElementById('radarSucursal'), { type: 'radar',
    data: { labels: RADAR_DEP.labels, datasets: [{ data: RADAR_DEP.data, backgroundColor: 'rgba(13,110,253,.2)', borderColor: '#0d6efd', pointBackgroundColor: '#0d6efd' }] }, options: radarOpts });
}
document.querySelectorAll('.radarDepto').forEach(cv => {
  const d = RADAR_CAT[cv.dataset.dep]; if (!d) return;
  new Chart(cv, { type: 'radar', data: { labels: d.labels, datasets: [{ data: d.data, backgroundColor: 'rgba(25,135,84,.2)', borderColor: '#198754', pointBackgroundColor: '#198754' }] }, options: radarOpts });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
