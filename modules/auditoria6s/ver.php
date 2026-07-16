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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT a.*, s.nombre AS sucursal, u.nombre_completo AS auditor
                       FROM auditorias_6s a
                       JOIN sucursales s ON s.id = a.sucursal_id
                       LEFT JOIN usuarios u ON u.id = a.auditor_id
                       WHERE a.id = ?");
$stmt->execute([$id]);
$aud = $stmt->fetch();
if (!$aud) { redirect('modules/auditoria6s/listar.php'); }
if (!$es_admin && $aud['sucursal_id'] != $usuario_sucursal_id) { redirect('modules/auditoria6s/listar.php'); }

$dstmt = $pdo->prepare("SELECT ad.departamento_id, ad.evaluacion_total, d.nombre
                        FROM auditorias_6s_departamentos ad JOIN departamentos d ON d.id = ad.departamento_id
                        WHERE ad.auditoria_id = ? ORDER BY d.nombre");
$dstmt->execute([$id]);
$deptos = $dstmt->fetchAll();

$det = $pdo->prepare("SELECT r.departamento_id, cat.id AS cat_id, cat.nombre AS categoria,
                             cr.id AS criterio_id, cr.texto,
                             r.calificacion, r.puntaje, r.prioridad, r.fecha_compromiso, r.comentarios, r.id AS resp_id
                      FROM auditorias_6s_respuestas r
                      JOIN criterios_6s cr ON cr.id = r.criterio_id
                      JOIN categorias_6s cat ON cat.id = cr.categoria_id
                      WHERE r.auditoria_id = ? ORDER BY cat.orden, cr.orden, cr.id");
$det->execute([$id]);
$detPorDep = [];
foreach ($det->fetchAll() as $f) { $detPorDep[$f['departamento_id']][] = $f; }

$fst = $pdo->prepare("SELECT f.departamento_id, e.nombre FROM auditorias_6s_firmantes f
                      JOIN empleados e ON e.id = f.empleado_id WHERE f.auditoria_id = ? ORDER BY e.nombre");
$fst->execute([$id]);
$firmPorDep = [];
foreach ($fst->fetchAll() as $row) { $firmPorDep[$row['departamento_id']][] = $row['nombre']; }

$labels = [1 => 'No cumple y desconoce', 2 => 'No cumple', 3 => 'Cumple, falta mejorar', 4 => 'Sí cumple'];
$semanaLabel = ($aud['anio'] && $aud['semana']) ? s6_label_semana((int)$aud['anio'], (int)$aud['semana']) : format_date_es($aud['fecha']);

include '../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
  <div>
    <h2 class="mb-1"><i class="fas fa-clipboard-check"></i> Auditoría 6S</h2>
    <div class="text-muted"><?= htmlspecialchars($aud['sucursal']) ?> · <?= htmlspecialchars($semanaLabel) ?>
      <?php if ($aud['estado'] === 'finalizada'): ?><span class="badge bg-success ms-1">Finalizada</span>
      <?php else: ?><span class="badge bg-warning text-dark ms-1">Borrador</span><?php endif; ?>
    </div>
    <div class="small text-muted">
      Auditor: <?= htmlspecialchars($aud['auditor'] ?? '—') ?> · <?= count($deptos) ?> áreas
      <?php if (!empty($aud['fecha_inicio'])): ?> · Inicio: <?= date('d/m/Y H:i', strtotime($aud['fecha_inicio'])) ?><?php endif; ?>
      <?php if (!empty($aud['fecha_fin'])): ?> · Fin: <?= date('d/m/Y H:i', strtotime($aud['fecha_fin'])) ?><?php endif; ?>
    </div>
  </div>
  <div class="text-end">
    <div class="text-muted small">Evaluación global (promedio de áreas)</div>
    <div class="display-6"><span class="badge bg-<?= color_6s($aud['evaluacion_total']) ?>"><?= $aud['evaluacion_total'] !== null ? number_format($aud['evaluacion_total'],1).'%' : '—' ?></span></div>
    <div class="mt-2 d-flex gap-2 justify-content-end">
      <a href="realizar.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i> Editar</a>
      <a href="imprimir.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-print"></i> Imprimir / PDF</a>
      <a href="listar.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> Volver</a>
    </div>
  </div>
</div>

<?php if (($_GET['msg'] ?? '') === 'finalizada'): ?><div class="alert alert-success">Auditoría finalizada correctamente.</div><?php endif; ?>

<div class="row g-2 mb-4">
  <?php foreach ($deptos as $d): ?>
  <div class="col-6 col-md-3 col-lg-2">
    <div class="card text-center h-100"><div class="card-body p-2">
      <div class="small text-muted text-truncate" title="<?= htmlspecialchars($d['nombre']) ?>"><?= htmlspecialchars($d['nombre']) ?></div>
      <div class="h5 mb-0"><span class="badge bg-<?= color_6s($d['evaluacion_total']) ?>"><?= $d['evaluacion_total'] !== null ? number_format($d['evaluacion_total'],1).'%' : '—' ?></span></div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<?php foreach ($deptos as $d): $depId = (int)$d['departamento_id']; $filas = $detPorDep[$depId] ?? [];
  $grupos = [];
  foreach ($filas as $f) { $grupos[$f['cat_id']]['nombre'] = $f['categoria']; $grupos[$f['cat_id']]['items'][] = $f; }
?>
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-layer-group"></i> <strong><?= htmlspecialchars($d['nombre']) ?></strong></span>
    <span class="badge bg-<?= color_6s($d['evaluacion_total']) ?>"><?= $d['evaluacion_total'] !== null ? number_format($d['evaluacion_total'],1).'%' : '—' ?></span>
  </div>
  <div class="card-body">
    <?php if (!empty($firmPorDep[$depId])): ?>
      <p class="small text-muted mb-2"><i class="fas fa-signature"></i> Firmantes: <?= htmlspecialchars(implode(', ', $firmPorDep[$depId])) ?></p>
    <?php endif; ?>
    <?php foreach ($grupos as $g): ?>
      <h6 class="text-primary mt-2"><?= htmlspecialchars($g['nombre']) ?></h6>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th style="min-width:200px;">Criterio</th><th class="text-center">Calificación</th><th class="text-center">Puntaje</th><th>Prioridad</th><th>Compromiso</th><th>Comentarios</th><th class="text-center">Fotos</th></tr></thead>
          <tbody>
            <?php foreach ($g['items'] as $f):
              $fotos = [];
              if ($f['resp_id']) { $ev = $pdo->prepare("SELECT nombre_archivo FROM auditorias_6s_evidencias WHERE respuesta_id = ?"); $ev->execute([$f['resp_id']]); $fotos = $ev->fetchAll(); } ?>
            <tr>
              <td><?= htmlspecialchars($f['texto']) ?></td>
              <td class="text-center"><?php if ($f['calificacion']): ?><span class="badge bg-<?= color_6s($f['puntaje']) ?>"><?= htmlspecialchars($labels[$f['calificacion']]) ?></span><?php else: ?><span class="text-muted">Sin contestar</span><?php endif; ?></td>
              <td class="text-center"><?= $f['puntaje'] !== null ? (int)$f['puntaje'] : 0 ?></td>
              <td><?= $f['prioridad'] ? htmlspecialchars($f['prioridad']) : '—' ?></td>
              <td><?= $f['fecha_compromiso'] ? format_date_es($f['fecha_compromiso']) : '—' ?></td>
              <td><?= $f['comentarios'] ? htmlspecialchars($f['comentarios']) : '—' ?></td>
              <td class="text-center"><?php if ($fotos): ?><div class="d-flex flex-wrap gap-1 justify-content-center"><?php foreach ($fotos as $ft): ?><a href="<?= UPLOAD_URL . htmlspecialchars($ft['nombre_archivo']) ?>" target="_blank"><img src="<?= UPLOAD_URL . htmlspecialchars($ft['nombre_archivo']) ?>" style="height:40px;width:40px;object-fit:cover;border-radius:.25rem;"></a><?php endforeach; ?></div><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php include '../../includes/footer.php'; ?>
