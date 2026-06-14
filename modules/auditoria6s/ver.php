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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT a.*, s.nombre AS sucursal, d.nombre AS departamento, u.nombre_completo AS auditor
                       FROM auditorias_6s a
                       JOIN sucursales s ON s.id = a.sucursal_id
                       JOIN departamentos d ON d.id = a.departamento_id
                       LEFT JOIN usuarios u ON u.id = a.auditor_id
                       WHERE a.id = ?");
$stmt->execute([$id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }
if (!$es_admin && $aud['sucursal_id'] != $usuario_sucursal_id) { redirect('modules/auditoria6s/listar.php'); }

// Detalle por criterio + categoría + respuesta + responsable
$det = $pdo->prepare("SELECT cat.id AS cat_id, cat.nombre AS categoria, cat.orden AS cat_orden,
                             cr.id AS criterio_id, cr.texto,
                             r.calificacion, r.puntaje, r.prioridad, r.dias_para_corregir, r.fecha_compromiso, r.comentarios, r.id AS resp_id
                      FROM criterios_6s cr
                      JOIN categorias_6s cat ON cat.id = cr.categoria_id
                      JOIN criterios_6s_departamento cd ON cd.criterio_id = cr.id AND cd.departamento_id = ?
                      LEFT JOIN auditorias_6s_respuestas r ON r.criterio_id = cr.id AND r.auditoria_id = ?
                      WHERE cr.activo = 1 AND cat.activo = 1
                      ORDER BY cat.orden, cr.orden, cr.id");
$det->execute([$aud['departamento_id'], $id]);
$filas = $det->fetchAll();

// Firmantes
$fst = $pdo->prepare("SELECT e.nombre FROM auditorias_6s_firmantes f JOIN empleados e ON e.id = f.empleado_id WHERE f.auditoria_id = ? ORDER BY e.nombre");
$fst->execute([$id]);
$firmantes = array_column($fst->fetchAll(), 'nombre');

// Promedios por categoría (no contestados = 0)
$grupos = [];
foreach ($filas as $f) {
    $cid = $f['cat_id'];
    if (!isset($grupos[$cid])) $grupos[$cid] = ['nombre' => $f['categoria'], 'items' => [], 'suma' => 0, 'n' => 0];
    $grupos[$cid]['items'][] = $f;
    $grupos[$cid]['suma'] += ($f['puntaje'] !== null ? $f['puntaje'] : 0);
    $grupos[$cid]['n']++;
}

$labels = [1 => 'No cumple y desconoce', 2 => 'No cumple', 3 => 'Cumple, falta mejorar', 4 => 'Sí cumple'];

include '../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
  <div>
    <h2 class="mb-1"><i class="fas fa-clipboard-check"></i> Auditoría 6S</h2>
    <div class="text-muted">
      <?= htmlspecialchars($aud['departamento']) ?> · <?= htmlspecialchars($aud['sucursal']) ?> · <?= format_date_es($aud['fecha']) ?>
      <?php if ($aud['estado'] === 'finalizada'): ?><span class="badge bg-success ms-1">Finalizada</span>
      <?php else: ?><span class="badge bg-warning text-dark ms-1">Borrador</span><?php endif; ?>
    </div>
    <div class="small text-muted">Auditor: <?= htmlspecialchars($aud['auditor'] ?? '—') ?></div>
  </div>
  <div class="text-end">
    <div class="text-muted small">Evaluación total</div>
    <div class="display-6"><span class="badge bg-<?= color_6s($aud['evaluacion_total']) ?>"><?= $aud['evaluacion_total'] !== null ? number_format($aud['evaluacion_total'],1).'%' : '—' ?></span></div>
    <div class="mt-2 d-flex gap-2 justify-content-end">
      <a href="realizar.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i> Editar</a>
      <a href="imprimir.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-print"></i> Imprimir / PDF</a>
      <a href="listar.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> Volver</a>
    </div>
  </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'finalizada'): ?>
  <div class="alert alert-success">Auditoría finalizada correctamente.</div>
<?php endif; ?>

<div class="row g-2 mb-3">
  <?php foreach ($grupos as $g): $prom = $g['n'] ? $g['suma'] / $g['n'] : 0; ?>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card text-center h-100">
      <div class="card-body p-2">
        <div class="small text-muted text-truncate" title="<?= htmlspecialchars($g['nombre']) ?>"><?= htmlspecialchars($g['nombre']) ?></div>
        <div class="h5 mb-0"><span class="badge bg-<?= color_6s($prom) ?>"><?= number_format($prom,1) ?>%</span></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (!empty($firmantes)): ?>
<div class="card mb-3"><div class="card-body py-2">
  <i class="fas fa-signature text-muted"></i> <strong>Firmantes:</strong>
  <?= htmlspecialchars($aud['auditor'] ?? 'Auditor') ?> (auditor)<?php foreach ($firmantes as $fn): ?>, <?= htmlspecialchars($fn) ?><?php endforeach; ?>
</div></div>
<?php endif; ?>

<?php foreach ($grupos as $g): ?>
<h5 class="mt-3"><?= htmlspecialchars($g['nombre']) ?></h5>
<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead>
      <tr>
        <th style="min-width:220px;">Criterio</th>
        <th class="text-center">Calificación</th>
        <th class="text-center">Puntaje</th>
        <th>Prioridad</th>
        <th>Compromiso</th>
        <th>Comentarios</th>
        <th class="text-center">Fotos</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($g['items'] as $f):
        $fotos = [];
        if ($f['resp_id']) {
            $ev = $pdo->prepare("SELECT nombre_archivo FROM auditorias_6s_evidencias WHERE respuesta_id = ?");
            $ev->execute([$f['resp_id']]);
            $fotos = $ev->fetchAll();
        }
      ?>
      <tr>
        <td><?= htmlspecialchars($f['texto']) ?></td>
        <td class="text-center">
          <?php if ($f['calificacion']): ?>
            <span class="badge bg-<?= color_6s($f['puntaje']) ?>"><?= htmlspecialchars($labels[$f['calificacion']]) ?></span>
          <?php else: ?><span class="text-muted">Sin contestar</span><?php endif; ?>
        </td>
        <td class="text-center"><?= $f['puntaje'] !== null ? (int)$f['puntaje'] : 0 ?></td>
        <td><?= $f['prioridad'] ? htmlspecialchars($f['prioridad']) : '—' ?></td>
        <td><?= $f['fecha_compromiso'] ? format_date_es($f['fecha_compromiso']) : '—' ?></td>
        <td><?= $f['comentarios'] ? htmlspecialchars($f['comentarios']) : '—' ?></td>
        <td class="text-center">
          <?php if ($fotos): ?>
            <div class="d-flex flex-wrap gap-1 justify-content-center">
            <?php foreach ($fotos as $ft): ?>
              <a href="<?= UPLOAD_URL . htmlspecialchars($ft['nombre_archivo']) ?>" target="_blank">
                <img src="<?= UPLOAD_URL . htmlspecialchars($ft['nombre_archivo']) ?>" style="height:40px;width:40px;object-fit:cover;border-radius:.25rem;">
              </a>
            <?php endforeach; ?>
            </div>
          <?php else: ?><span class="text-muted">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>

<?php include '../../includes/footer.php'; ?>