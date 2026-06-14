<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$es_admin = ($usuario_rol === 'admin');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$auditoria = null;
$respuestas = []; // criterio_id => row

if ($id) {
    $stmt = $pdo->prepare("SELECT a.*, s.nombre AS sucursal, d.nombre AS departamento
                           FROM auditorias_6s a
                           JOIN sucursales s ON s.id = a.sucursal_id
                           JOIN departamentos d ON d.id = a.departamento_id
                           WHERE a.id = ?");
    $stmt->execute([$id]);
    $auditoria = $stmt->fetch();

    if (!$auditoria) { redirect('modules/auditoria6s/listar.php'); }
    // Alcance por sucursal
    if (!$es_admin && $auditoria['sucursal_id'] != $usuario_sucursal_id) {
        redirect('modules/auditoria6s/listar.php');
    }

    // Respuestas existentes
    $r = $pdo->prepare("SELECT * FROM auditorias_6s_respuestas WHERE auditoria_id = ?");
    $r->execute([$id]);
    foreach ($r->fetchAll() as $row) { $respuestas[$row['criterio_id']] = $row; }
}

// ---- Pantalla de inicio (sin auditoría seleccionada) ----
if (!$auditoria) {
    if ($es_admin) {
        $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
    } else {
        $sucursales = $pdo->prepare("SELECT id, nombre FROM sucursales WHERE id = ?");
        $sucursales->execute([$usuario_sucursal_id]);
        $sucursales = $sucursales->fetchAll();
    }
    // Solo departamentos que tienen al menos un criterio activo asignado
    $departamentos = $pdo->query("SELECT DISTINCT d.id, d.nombre
                                  FROM departamentos d
                                  JOIN criterios_6s_departamento cd ON cd.departamento_id = d.id
                                  JOIN criterios_6s cr ON cr.id = cd.criterio_id AND cr.activo = 1
                                  WHERE d.activo = 1
                                  ORDER BY d.nombre")->fetchAll();

    include '../../includes/header.php';
    ?>
    <h2><i class="fas fa-clipboard-check"></i> Nueva auditoría 6S</h2>
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card card-body">
                <form method="post" action="guardar.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="accion" value="crear">
                    <div class="mb-3">
                        <label class="form-label">Sucursal</label>
                        <select name="sucursal_id" class="form-select" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Departamento</label>
                        <select name="departamento_id" class="form-select" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button class="btn btn-primary w-100"><i class="fas fa-play"></i> Iniciar auditoría</button>
                    <a href="listar.php" class="btn btn-link w-100">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
    <?php
    include '../../includes/footer.php';
    exit;
}

// ---- Captura: criterios del departamento agrupados por categoría ----
$cstmt = $pdo->prepare("SELECT cr.id, cr.texto, cat.id AS cat_id, cat.nombre AS cat_nombre, cat.orden AS cat_orden
                        FROM criterios_6s cr
                        JOIN categorias_6s cat ON cat.id = cr.categoria_id
                        JOIN criterios_6s_departamento cd ON cd.criterio_id = cr.id AND cd.departamento_id = ?
                        WHERE cr.activo = 1 AND cat.activo = 1
                        ORDER BY cat.orden, cr.orden, cr.id");
$cstmt->execute([$auditoria['departamento_id']]);
$criterios = $cstmt->fetchAll();

// Agrupar por categoría
$grupos = [];
foreach ($criterios as $c) {
    $grupos[$c['cat_id']]['nombre'] = $c['cat_nombre'];
    $grupos[$c['cat_id']]['items'][] = $c;
}
$total_criterios = count($criterios);

// Firmantes disponibles = empleados del departamento auditado en esa sucursal
$rstmt = $pdo->prepare("SELECT id, nombre FROM empleados
                        WHERE departamento_id = ? AND sucursal_id = ? AND activo = 1
                        ORDER BY nombre");
$rstmt->execute([$auditoria['departamento_id'], $auditoria['sucursal_id']]);
$emp_depto = $rstmt->fetchAll();

// Firmantes ya seleccionados
$fstmt = $pdo->prepare("SELECT empleado_id FROM auditorias_6s_firmantes WHERE auditoria_id = ?");
$fstmt->execute([$auditoria['id']]);
$firmantes_sel = array_map('intval', array_column($fstmt->fetchAll(), 'empleado_id'));

$labels = [1 => 'No cumple y desconoce', 2 => 'No cumple', 3 => 'Cumple, falta mejorar', 4 => 'Sí cumple'];
$btnclass = [1 => 'danger', 2 => 'warning', 3 => 'info', 4 => 'success'];

include '../../includes/header.php';
?>
<style>
.s6-cat-head { font-weight:600; }
.s6-crit { border:1px solid #e3e6ea; border-radius:.5rem; padding:.75rem; margin-bottom:.75rem; background:#fff; }
.s6-opts .btn { font-size:.8rem; }
.s6-sticky { position:sticky; top:0; z-index:1020; background:#fff; border-bottom:1px solid #dee2e6; padding:.5rem .25rem; margin:-.5rem -.25rem .75rem; }
@media (max-width:576px){ .s6-opts .btn { font-size:.72rem; padding:.35rem .25rem; } }
</style>

<div class="s6-sticky">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <div class="fw-bold"><i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($auditoria['departamento']) ?></div>
      <small class="text-muted"><?= htmlspecialchars($auditoria['sucursal']) ?> · <?= format_date_es($auditoria['fecha']) ?>
        <?php if ($auditoria['estado'] === 'finalizada'): ?><span class="badge bg-success ms-1">Finalizada</span><?php endif; ?>
      </small>
    </div>
    <div class="text-end">
      <div class="h5 mb-0">Eval: <span id="s6-prom" class="badge bg-secondary">0%</span></div>
      <small class="text-muted"><span id="s6-cont">0</span>/<?= $total_criterios ?> contestados</small>
    </div>
  </div>
</div>

<?php if (($_GET['msg'] ?? '') === 'guardada'): ?>
  <div class="alert alert-success alert-dismissible">Borrador guardado correctamente.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (($_GET['err'] ?? '') === 'incompleto'): ?>
  <div class="alert alert-danger alert-dismissible">
    <i class="fas fa-exclamation-circle"></i> No se puede finalizar: faltan <strong><?= (int)($_GET['faltan'] ?? 0) ?></strong> criterio(s) por contestar. Se guardó como borrador.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($auditoria['estado'] === 'finalizada'): ?>
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> Esta auditoría ya está <strong>finalizada</strong>. Si guardas cambios, se actualizará el registro y se recalculará la evaluación.
  </div>
<?php endif; ?>

<form method="post" action="guardar.php" enctype="multipart/form-data" id="s6-form">
<input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
<input type="hidden" name="accion" value="guardar">
<input type="hidden" name="auditoria_id" value="<?= (int)$auditoria['id'] ?>">
<input type="hidden" name="finalizar" id="s6-finalizar" value="0">
<input type="hidden" id="s6-fecha-aud" value="<?= htmlspecialchars($auditoria['fecha']) ?>">

<div class="accordion" id="s6-acc">
<?php $gi = 0; foreach ($grupos as $cat_id => $g): $gi++; ?>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button <?= $gi === 1 ? '' : 'collapsed' ?> s6-cat-head" type="button"
              data-bs-toggle="collapse" data-bs-target="#cat<?= $cat_id ?>">
        <?= htmlspecialchars($g['nombre']) ?> <span class="badge bg-light text-dark ms-2"><?= count($g['items']) ?></span>
      </button>
    </h2>
    <div id="cat<?= $cat_id ?>" class="accordion-collapse collapse <?= $gi === 1 ? 'show' : '' ?>" data-bs-parent="#s6-acc">
      <div class="accordion-body p-2">
        <?php foreach ($g['items'] as $c):
            $cid = $c['id'];
            $resp = $respuestas[$cid] ?? null;
            $calif = $resp['calificacion'] ?? null;
        ?>
        <div class="s6-crit">
          <div class="mb-2"><?= htmlspecialchars($c['texto']) ?></div>
          <div class="btn-group s6-opts w-100 mb-2" role="group">
            <?php foreach ($labels as $val => $txt): ?>
              <input type="radio" class="btn-check s6-calif" name="calif[<?= $cid ?>]" id="c<?= $cid ?>_<?= $val ?>"
                     value="<?= $val ?>" autocomplete="off" <?= (int)$calif === $val ? 'checked' : '' ?>>
              <label class="btn btn-outline-<?= $btnclass[$val] ?>" for="c<?= $cid ?>_<?= $val ?>"><?= $txt ?></label>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-bs-toggle="collapse" data-bs-target="#extra<?= $cid ?>">
            <i class="fas fa-sliders-h"></i> Detalle / foto
          </button>
          <div id="extra<?= $cid ?>" class="collapse mt-2">
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Prioridad</label>
                <select name="prioridad[<?= $cid ?>]" class="form-select form-select-sm">
                  <option value="">—</option>
                  <?php foreach (['Urgente','Normal','No urgente'] as $p): ?>
                    <option value="<?= $p ?>" <?= ($resp['prioridad'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6 col-md-3">
                <input type="number" min="0" name="dias[<?= $cid ?>]" class="form-control form-control-sm s6-dias"
                       data-cid="<?= $cid ?>" value="<?= htmlspecialchars($resp['dias_para_corregir'] ?? '') ?>">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Fecha compromiso</label>
                <input type="text" id="fc<?= $cid ?>" class="form-control form-control-sm" readonly
                       value="<?= $resp && $resp['fecha_compromiso'] ? format_date_es($resp['fecha_compromiso']) : '' ?>">
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Comentarios</label>
                <textarea name="coment[<?= $cid ?>]" rows="2" class="form-control form-control-sm"><?= htmlspecialchars($resp['comentarios'] ?? '') ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label small mb-1"><i class="fas fa-camera"></i> Fotos</label>
                <input type="file" name="foto<?= $cid ?>[]" class="form-control form-control-sm" accept="image/*" capture="environment" multiple>
                <?php if ($resp):
                    $ev = $pdo->prepare("SELECT id, nombre_archivo FROM auditorias_6s_evidencias WHERE respuesta_id = ?");
                    $ev->execute([$resp['id']]);
                    $fotos = $ev->fetchAll();
                    if ($fotos): ?>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                      <?php foreach ($fotos as $f): ?>
                        <a href="<?= UPLOAD_URL . htmlspecialchars($f['nombre_archivo']) ?>" target="_blank">
                          <img src="<?= UPLOAD_URL . htmlspecialchars($f['nombre_archivo']) ?>" style="height:64px;width:64px;object-fit:cover;border-radius:.35rem;">
                        </a>
                      <?php endforeach; ?>
                    </div>
                    <?php endif;
                endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="card my-3">
  <div class="card-header"><i class="fas fa-signature"></i> Firmantes de conformidad</div>
  <div class="card-body">
    <p class="small text-muted mb-2">Selecciona los responsables del departamento que firmarán la hoja. El auditor se incluye automáticamente.</p>
    <?php if (empty($emp_depto)): ?>
      <div class="alert alert-warning mb-0 small">No hay empleados activos en este departamento y sucursal. Regístralos en Catálogos → Empleados para poder seleccionarlos como firmantes.</div>
    <?php else: ?>
      <div class="row g-1">
      <?php foreach ($emp_depto as $e): ?>
        <div class="col-12 col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="firmantes[]" value="<?= $e['id'] ?>"
                   id="firm<?= $e['id'] ?>" <?= in_array((int)$e['id'], $firmantes_sel, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="firm<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></label>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="d-flex gap-2 my-3 sticky-bottom bg-white py-2">
  <button type="submit" class="btn btn-outline-secondary flex-fill" onclick="document.getElementById('s6-finalizar').value='0'">
    <i class="fas fa-save"></i> Guardar borrador
  </button>
  <button type="submit" class="btn btn-success flex-fill"
          onclick="return finalizar6s();">
    <i class="fas fa-check-circle"></i> Finalizar
  </button>
</div>
</form>

<script>
const S6_TOTAL = <?= $total_criterios ?>;
function recalc6s() {
  const grupos = {};
  document.querySelectorAll('.s6-calif:checked').forEach(el => {
    grupos[el.name] = parseInt(el.value, 10);
  });
  const llaves = Object.keys(grupos);
  let suma = 0;
  llaves.forEach(k => suma += grupos[k] * 25);
  const cont = llaves.length;
  const prom = cont ? (suma / cont) : 0; // promedio de contestados (vista en vivo)
  document.getElementById('s6-cont').textContent = cont;
  const badge = document.getElementById('s6-prom');
  badge.textContent = prom.toFixed(1) + '%';
  badge.className = 'badge bg-' + (prom >= 85 ? 'success' : prom >= 70 ? 'info' : prom >= 50 ? 'warning' : (cont ? 'danger' : 'secondary'));
}
document.querySelectorAll('.s6-calif').forEach(el => el.addEventListener('change', recalc6s));

// Fecha compromiso = fecha auditoría + días
const fechaAud = document.getElementById('s6-fecha-aud').value;
function calcFC(cid, dias) {
  const fc = document.getElementById('fc' + cid);
  if (!dias || isNaN(dias) || !fechaAud) { fc.value = ''; return; }
  const d = new Date(fechaAud + 'T00:00:00');
  d.setDate(d.getDate() + parseInt(dias, 10));
  const dd = String(d.getDate()).padStart(2,'0'), mm = String(d.getMonth()+1).padStart(2,'0');
  fc.value = dd + '/' + mm + '/' + d.getFullYear();
}
document.querySelectorAll('.s6-dias').forEach(inp => {
  inp.addEventListener('input', () => calcFC(inp.dataset.cid, inp.value));
});

function finalizar6s() {
  const cont = document.querySelectorAll('.s6-calif:checked').length;
  if (cont < S6_TOTAL) {
    alert('No puedes finalizar: faltan ' + (S6_TOTAL - cont) + ' criterio(s) por contestar. Debes calificar todos los criterios.');
    return false;
  }
  document.getElementById('s6-finalizar').value = '1';
  return true;
}
recalc6s();
</script>

<?php include '../../includes/footer.php'; ?>