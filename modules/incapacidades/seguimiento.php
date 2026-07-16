<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_inc.php';

if ($usuario_rol !== 'admin' && $usuario_rol !== 'supervisor') {
    redirect('modules/dashboard/');
}

$reporte_id = isset($_GET['reporte_id']) ? (int) $_GET['reporte_id'] : 0;
if (!$reporte_id) { redirect('modules/incapacidades/listar.php'); }

$rep = cargar_reporte_incapacidad($pdo, $reporte_id, $usuario_rol, $usuario_sucursal_id ?? null);

// Tramos
$st = $pdo->prepare("SELECT * FROM incapacidad_tramos WHERE reporte_id = ? ORDER BY fecha_inicio, id");
$st->execute([$reporte_id]);
$tramos = $st->fetchAll();

// Total de tramos (suma real de la tabla)
$totalTramos = array_sum(array_column($tramos, 'dias'));
// Total a mostrar: si hay tramos usa su suma; si no, cae al valor capturado en el reporte
// (dias_perdidos manual del accidente). Así no muestra 0 cuando aún no se cargan tramos.
$totalDias = $totalTramos > 0 ? $totalTramos : (int)($rep['dias_perdidos'] ?? 0);

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$cerrado = !empty($rep['fecha_regreso']);

$emisores = ['IMSS' => 'IMSS', 'particular' => 'Médico particular', 'otro' => 'Otro'];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h2 class="mb-0"><i class="fas fa-stethoscope text-primary"></i> Seguimiento de incapacidad</h2>
  <a href="listar.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-warning"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Datos del accidente -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="text-muted small text-uppercase">Accidente</div>
        <div class="fw-semibold">#<?= (int)$rep['id'] ?> · <?= date('d/m/Y', strtotime($rep['fecha'])) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small text-uppercase">Empleado</div>
        <div class="fw-semibold"><?= htmlspecialchars($rep['numero_empleado'] . ' - ' . $rep['empleado_nombre']) ?></div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small text-uppercase">Sucursal</div>
        <div class="fw-semibold"><?= htmlspecialchars($rep['sucursal']) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted small text-uppercase">Total días incap.</div>
        <div class="display-6 fw-bold text-primary"><?= (int)$totalDias ?></div>
        <?php if ($totalTramos === 0 && $totalDias > 0): ?>
          <div class="small text-muted">Capturados en el reporte</div>
        <?php else: ?>
          <div class="small text-muted">Suma de <?= count($tramos) ?> tramo(s)</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted small text-uppercase">Estado</div>
        <div class="mt-2">
          <?php if ($cerrado): ?>
            <span class="badge bg-secondary fs-6 px-3 py-2"><i class="fas fa-circle-check"></i> Cerrado</span>
          <?php else: ?>
            <span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="fas fa-hourglass-half"></i> En seguimiento</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted small text-uppercase">Tramos</div>
        <div class="display-6 fw-bold"><?= count($tramos) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted small text-uppercase">Regreso a labores</div>
        <div class="h5 mb-2 mt-1"><?= $cerrado ? date('d/m/Y', strtotime($rep['fecha_regreso'])) : '—' ?></div>
        <?php if ($cerrado): ?>
          <form action="reabrir_seguimiento.php" method="post" onsubmit="return confirm('¿Reabrir el seguimiento? Se borrará la fecha de regreso.');">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="reporte_id" value="<?= (int)$rep['id'] ?>">
            <button class="btn btn-outline-warning btn-sm w-100"><i class="fas fa-rotate-left"></i> Reabrir</button>
          </form>
        <?php else: ?>
          <button class="btn btn-success btn-sm w-100" type="button" data-bs-toggle="modal" data-bs-target="#cerrarModal"><i class="fas fa-check"></i> Registrar regreso</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Agregar tramo -->
<?php if (!$cerrado): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold"><i class="fas fa-plus text-success"></i> Registrar tramo de incapacidad</div>
  <div class="card-body">
    <form action="agregar_tramo.php" method="post" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      <input type="hidden" name="reporte_id" value="<?= (int)$rep['id'] ?>">
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Fecha inicio</label>
        <input type="date" name="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Días</label>
        <input type="number" name="dias" min="1" max="365" inputmode="numeric" class="form-control" required>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Emisor</label>
        <select name="emisor" class="form-select">
          <?php foreach ($emisores as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Folio (opcional)</label>
        <input type="text" name="folio" class="form-control" maxlength="80">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1">Documento (obligatorio)</label>
        <input type="file" name="documento" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
      </div>
      <div class="col-12 col-md-1 d-grid">
        <label class="form-label small mb-1 d-none d-md-block">&nbsp;</label>
        <button class="btn btn-primary"><i class="fas fa-save"></i><span class="d-md-none"> Guardar tramo</span></button>
      </div>
      <div class="col-12">
        <small class="text-muted">El documento (ST7 del IMSS, receta o constancia médica) es obligatorio para cada tramo. PDF/JPG/PNG.</small>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Tramos -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold"><i class="fas fa-list text-primary"></i> Tramos registrados (<?= count($tramos) ?>)</div>
  <div class="card-body">
    <?php if (empty($tramos)): ?>
      <div class="text-center text-muted py-4">
        <i class="fas fa-notes-medical fa-2x mb-2 d-block opacity-50"></i>
        Aún no hay tramos.<?php if (!$cerrado): ?> Registra el primero con la incapacidad inicial.<?php endif; ?>
        <?php if ($totalDias > 0): ?>
          <div class="small mt-2">Este accidente tiene <strong><?= (int)$totalDias ?></strong> día(s) capturados en el reporte. Al registrar tramos, el total se recalcula automáticamente.</div>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr><th>Inicio</th><th class="text-center">Días</th><th>Emisor</th><th>Folio</th><th>Documento</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($tramos as $t): ?>
          <tr>
            <td><?= date('d/m/Y', strtotime($t['fecha_inicio'])) ?></td>
            <td class="text-center"><span class="badge bg-primary-subtle text-primary-emphasis"><?= (int)$t['dias'] ?></span></td>
            <td><?= htmlspecialchars($emisores[$t['emisor']] ?? $t['emisor']) ?></td>
            <td><?= htmlspecialchars($t['folio'] ?: '—') ?></td>
            <td><a href="<?= UPLOAD_URL . htmlspecialchars($t['nombre_archivo']) ?>" target="_blank" rel="noopener"><i class="fas <?= $t['tipo_archivo'] === 'imagen' ? 'fa-image' : 'fa-file-pdf' ?>"></i> Ver</a></td>
            <td class="text-end">
              <?php if (!$cerrado): ?>
              <form action="eliminar_tramo.php" method="post" onsubmit="return confirm('¿Eliminar este tramo?');" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="tramo_id" value="<?= (int)$t['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="table-light"><th>Total tramos</th><th class="text-center"><?= (int)$totalTramos ?></th><th colspan="4"></th></tr></tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal cerrar -->
<div class="modal fade" id="cerrarModal" tabindex="-1">
  <div class="modal-dialog">
    <form action="cerrar_seguimiento.php" method="post" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      <input type="hidden" name="reporte_id" value="<?= (int)$rep['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Registrar regreso a labores</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label">Fecha de regreso <span class="text-danger">*</span></label>
        <input type="date" name="fecha_regreso" class="form-control" value="<?= date('Y-m-d') ?>" required>
        <small class="text-muted">Cerrará el seguimiento. Podrás reabrirlo si es necesario.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Confirmar regreso</button>
      </div>
    </form>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
