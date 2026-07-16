<?php
/**
 * Detalle por semana 6S (modelo por sucursal).
 * Para la semana elegida (año+semana, por defecto la actual): cumplimiento por
 * departamento desde auditorias_6s_departamentos, desglose por categoría y
 * comparativo contra la semana anterior. Solo auditorías 'finalizada'.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_semanas.php';

$es_admin = ($usuario_rol === 'admin');
function color_6s($p) {
    if ($p === null || $p === '') return 'secondary';
    if ($p >= 85) return 'success';
    if ($p >= 70) return 'info';
    if ($p >= 50) return 'warning';
    return 'danger';
}

[$anioAct, $semAct] = s6_semana_actual();
$anio = (int)($_GET['anio'] ?? $anioAct);
$sem  = (int)($_GET['sem'] ?? $semAct);
if ($sem < 1 || $sem > 53) $sem = $semAct;

$ini = s6_lunes($anio, $sem); $fin = s6_domingo($anio, $sem);
// Semana anterior
$prevDate = date('Y-m-d', strtotime($ini . ' -7 days'));
[$anioP, $semP] = s6_semana_actual($prevDate);
$iniP = s6_lunes($anioP, $semP); $finP = s6_domingo($anioP, $semP);

if ($es_admin) {
    $f_sucursal = ($_GET['sucursal_id'] ?? '') !== '' ? (int)$_GET['sucursal_id'] : '';
    $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
} else {
    $f_sucursal = (int)$usuario_sucursal_id; $sucursales = [];
}

$scope = "a.estado = 'finalizada'"; $pSuc = [];
if ($f_sucursal) { $scope .= " AND a.sucursal_id = ?"; $pSuc[] = $f_sucursal; }

function prom_por_depto(PDO $pdo, string $scope, array $pSuc, string $ini, string $fin): array {
    $sql = "SELECT ad.departamento_id AS dep, ROUND(AVG(ad.evaluacion_total),1) AS prom, COUNT(*) AS n
            FROM auditorias_6s a JOIN auditorias_6s_departamentos ad ON ad.auditoria_id = a.id
            WHERE $scope AND a.fecha BETWEEN ? AND ? GROUP BY ad.departamento_id";
    $st = $pdo->prepare($sql); $st->execute(array_merge($pSuc, [$ini, $fin]));
    $out = [];
    foreach ($st->fetchAll() as $r) $out[$r['dep']] = ['prom' => (float)$r['prom'], 'n' => (int)$r['n']];
    return $out;
}
$actual = prom_por_depto($pdo, $scope, $pSuc, $ini, $fin);
$previa = prom_por_depto($pdo, $scope, $pSuc, $iniP, $finP);

// Desglose por categoría (semana actual)
$sql = "SELECT r.departamento_id AS dep, cat.nombre AS categoria, cat.orden, ROUND(AVG(COALESCE(r.puntaje,0)),1) AS prom
        FROM auditorias_6s a
        JOIN auditorias_6s_respuestas r ON r.auditoria_id = a.id
        JOIN criterios_6s cr ON cr.id = r.criterio_id
        JOIN categorias_6s cat ON cat.id = cr.categoria_id
        WHERE $scope AND a.fecha BETWEEN ? AND ? GROUP BY r.departamento_id, cat.id ORDER BY cat.orden";
$st = $pdo->prepare($sql); $st->execute(array_merge($pSuc, [$ini, $fin]));
$catPorDep = [];
foreach ($st->fetchAll() as $r) { $catPorDep[$r['dep']][] = ['cat' => $r['categoria'], 'prom' => (float)$r['prom']]; }

$depNombres = [];
foreach ($pdo->query("SELECT id, nombre FROM departamentos")->fetchAll() as $d) $depNombres[$d['id']] = $d['nombre'];

// Auditorías de la semana
$sql = "SELECT a.id, a.fecha, a.fecha_inicio, a.fecha_fin, a.evaluacion_total, s.nombre AS sucursal, u.nombre_completo AS auditor,
               (SELECT COUNT(*) FROM auditorias_6s_departamentos ad WHERE ad.auditoria_id = a.id) AS n_areas
        FROM auditorias_6s a JOIN sucursales s ON s.id = a.sucursal_id LEFT JOIN usuarios u ON u.id = a.auditor_id
        WHERE $scope AND a.fecha BETWEEN ? AND ? ORDER BY s.nombre";
$st = $pdo->prepare($sql); $st->execute(array_merge($pSuc, [$ini, $fin]));
$auditorias = $st->fetchAll();

function ds_opt_sem($sel) { $h=''; for($w=1;$w<=53;$w++){$h.='<option value="'.$w.'" '.($w===$sel?'selected':'').'>Semana '.$w.'</option>';} return $h; }
function ds_opt_anio($sel,$act){ $h=''; for($y=$act+1;$y>=$act-3;$y--){$h.='<option value="'.$y.'" '.($y===$sel?'selected':'').'>'.$y.'</option>';} return $h; }

include '../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h2 class="mb-0"><i class="fas fa-calendar-week"></i> Detalle semanal 6S</h2>
  <a href="listar.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> Ver auditorías</a>
</div>

<form method="get" class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-4 col-md-2"><label class="form-label small mb-1">Año</label><select name="anio" class="form-select form-select-sm" onchange="this.form.submit()"><?= ds_opt_anio($anio,$anioAct) ?></select></div>
    <div class="col-8 col-md-3"><label class="form-label small mb-1">Semana</label><select name="sem" class="form-select form-select-sm" onchange="this.form.submit()"><?= ds_opt_sem($sem) ?></select></div>
    <?php if ($es_admin): ?>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Sucursal</label>
      <select name="sucursal_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Todas</option>
        <?php foreach ($sucursales as $s): ?><option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="col-12 col-md-4 text-md-end"><span class="text-muted small"><?= htmlspecialchars(s6_label_semana($anio,$sem)) ?> · comparada con <?= htmlspecialchars(s6_label_semana($anioP,$semP)) ?></span></div>
  </div>
</form>

<?php if (empty($actual) && empty($auditorias)): ?>
  <div class="alert alert-info">No hay auditorías finalizadas en esta semana para el alcance seleccionado.</div>
<?php else: ?>

<div class="card mb-3">
  <div class="card-header">Cumplimiento por departamento (vs semana anterior)</div>
  <div class="card-body table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Departamento</th><th class="text-center">Eval.</th><th class="text-center">Cumplimiento</th><th class="text-center">Δ vs anterior</th><th>Por categoría</th></tr></thead>
      <tbody>
        <?php foreach ($actual as $dep => $info):
          $prev = $previa[$dep]['prom'] ?? null;
          $delta = $prev !== null ? round($info['prom'] - $prev, 1) : null; ?>
          <tr>
            <td><strong><?= htmlspecialchars($depNombres[$dep] ?? ('Depto ' . $dep)) ?></strong></td>
            <td class="text-center"><?= $info['n'] ?></td>
            <td class="text-center"><span class="badge bg-<?= color_6s($info['prom']) ?>"><?= $info['prom'] ?>%</span></td>
            <td class="text-center">
              <?php if ($delta === null): ?><span class="text-muted">—</span>
              <?php elseif ($delta > 0): ?><span class="text-success">▲ +<?= $delta ?></span>
              <?php elseif ($delta < 0): ?><span class="text-danger">▼ <?= $delta ?></span>
              <?php else: ?><span class="text-muted">=</span><?php endif; ?>
            </td>
            <td><?php foreach (($catPorDep[$dep] ?? []) as $c): ?><span class="badge bg-<?= color_6s($c['prom']) ?>" title="<?= htmlspecialchars($c['cat']) ?>"><?= htmlspecialchars(mb_substr($c['cat'],0,3)) ?>: <?= $c['prom'] ?></span> <?php endforeach; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">Auditorías de la semana (<?= count($auditorias) ?>)</div>
  <div class="card-body table-responsive">
    <table class="table table-sm table-hover align-middle">
      <thead><tr><th>Sucursal</th><th class="text-center">Áreas</th><th>Auditor</th><th>Inicio</th><th>Fin</th><th class="text-center">Global</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($auditorias as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['sucursal']) ?></td>
            <td class="text-center"><?= (int)$a['n_areas'] ?></td>
            <td><?= htmlspecialchars($a['auditor'] ?? '—') ?></td>
            <td class="small"><?= $a['fecha_inicio'] ? date('d/m H:i', strtotime($a['fecha_inicio'])) : '—' ?></td>
            <td class="small"><?= $a['fecha_fin'] ? date('d/m H:i', strtotime($a['fecha_fin'])) : '—' ?></td>
            <td class="text-center"><span class="badge bg-<?= color_6s($a['evaluacion_total']) ?>"><?= $a['evaluacion_total'] !== null ? $a['evaluacion_total'].'%' : '—' ?></span></td>
            <td class="text-end"><a href="ver.php?id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
