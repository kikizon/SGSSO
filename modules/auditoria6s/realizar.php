<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once __DIR__ . '/_semanas.php';

$es_admin = ($usuario_rol === 'admin');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$auditoria = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT a.*, s.nombre AS sucursal FROM auditorias_6s a
                           JOIN sucursales s ON s.id = a.sucursal_id WHERE a.id = ?");
    $stmt->execute([$id]);
    $auditoria = $stmt->fetch();
    if (!$auditoria) { redirect('modules/auditoria6s/listar.php'); }
    if (!$es_admin && $auditoria['sucursal_id'] != $usuario_sucursal_id) { redirect('modules/auditoria6s/listar.php'); }
}

// ---- Pantalla de inicio: sucursal + semana ----
if (!$auditoria) {
    if ($es_admin) {
        $sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
    } else {
        $sucursales = $pdo->prepare("SELECT id, nombre FROM sucursales WHERE id = ?");
        $sucursales->execute([$usuario_sucursal_id]);
        $sucursales = $sucursales->fetchAll();
    }
    [$anioActual, $semActual] = s6_semana_actual();
    $anioSel = (int)($_GET['anio'] ?? $anioActual);
    if ($anioSel < 2020 || $anioSel > $anioActual + 1) $anioSel = $anioActual;
    $numSemanas = s6_semanas_en_anio($anioSel);
    $semDefault = ($anioSel === $anioActual) ? $semActual : 1;

    include '../../includes/header.php';
    ?>
    <h2><i class="fas fa-clipboard-check"></i> Nueva auditoría 6S</h2>
    <p class="text-muted">Una auditoría abarca <strong>todas las áreas</strong> de la sucursal durante una <strong>semana</strong>. El inicio y fin se registran automáticamente.</p>
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card card-body">
                <form method="get" id="formAnio" class="mb-2">
                    <label class="form-label">Año</label>
                    <select name="anio" class="form-select" onchange="document.getElementById('formAnio').submit()">
                        <?php for ($y = $anioActual + 1; $y >= $anioActual - 2; $y--): ?>
                            <option value="<?= $y ?>" <?= $y === $anioSel ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
                <form method="post" action="guardar.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="anio" value="<?= $anioSel ?>">
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
                        <label class="form-label">Semana</label>
                        <select name="semana" class="form-select" required>
                            <?php for ($w = 1; $w <= $numSemanas; $w++): ?>
                                <option value="<?= $w ?>" <?= $w === $semDefault ? 'selected' : '' ?>><?= htmlspecialchars(s6_label_semana($anioSel, $w)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100"><i class="fas fa-play"></i> Iniciar auditoría</button>
                    <a href="listar.php" class="btn btn-link w-100">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
    <?php include '../../includes/footer.php'; exit;
}

// ---- Captura ----
$departamentos = $pdo->query("SELECT DISTINCT d.id, d.nombre
                              FROM departamentos d
                              JOIN criterios_6s_departamento cd ON cd.departamento_id = d.id
                              JOIN criterios_6s cr ON cr.id = cd.criterio_id AND cr.activo = 1
                              WHERE d.activo = 1 ORDER BY d.nombre")->fetchAll();

$critStmt = $pdo->prepare("SELECT cr.id, cr.texto, cat.id AS cat_id, cat.nombre AS cat_nombre
                           FROM criterios_6s cr
                           JOIN categorias_6s cat ON cat.id = cr.categoria_id AND cat.activo = 1
                           JOIN criterios_6s_departamento cd ON cd.criterio_id = cr.id AND cd.departamento_id = ?
                           WHERE cr.activo = 1 ORDER BY cat.orden, cr.orden, cr.id");

$r = $pdo->prepare("SELECT * FROM auditorias_6s_respuestas WHERE auditoria_id = ?");
$r->execute([$id]);
$resp = [];
foreach ($r->fetchAll() as $row) { $resp[$row['departamento_id']][$row['criterio_id']] = $row; }

$f = $pdo->prepare("SELECT departamento_id, empleado_id FROM auditorias_6s_firmantes WHERE auditoria_id = ?");
$f->execute([$id]);
$firmDep = [];
foreach ($f->fetchAll() as $row) { $firmDep[$row['departamento_id']][] = (int)$row['empleado_id']; }

$empStmt = $pdo->prepare("SELECT id, nombre FROM empleados WHERE departamento_id = ? AND sucursal_id = ? AND activo = 1 ORDER BY nombre");

// Botones de calificación: valor => [etiqueta, clase outline]
$botones = [
    '1'  => ['No cumple y desconoce', 'danger'],
    '2'  => ['No cumple',             'warning'],
    '3'  => ['Cumple, falta mejorar', 'info'],
    '4'  => ['Sí cumple',             'success'],
    'na' => ['N.A.',                  'secondary'],
];

$total_criterios = 0;
$semanaLabel = ($auditoria['anio'] && $auditoria['semana']) ? s6_label_semana((int)$auditoria['anio'], (int)$auditoria['semana']) : format_date_es($auditoria['fecha']);
$finalizada = ($auditoria['estado'] === 'finalizada');

// Pre-cálculo de criterios por departamento (para render y para no repetir queries)
$depData = [];
foreach ($departamentos as $dep) {
    $depId = (int)$dep['id'];
    $critStmt->execute([$depId]);
    $criterios = $critStmt->fetchAll();
    if (empty($criterios)) continue;
    $grupos = [];
    foreach ($criterios as $c) { $grupos[$c['cat_id']]['nombre'] = $c['cat_nombre']; $grupos[$c['cat_id']]['items'][] = $c; }
    $total_criterios += count($criterios);
    $empStmt->execute([$depId, $auditoria['sucursal_id']]);
    $depData[] = [
        'id' => $depId, 'nombre' => $dep['nombre'], 'grupos' => $grupos,
        'count' => count($criterios), 'empleados' => $empStmt->fetchAll(),
        'firmas' => $firmDep[$depId] ?? [],
    ];
}

include '../../includes/header.php';
?>
<style>
  .s6-steps{display:flex;flex-wrap:wrap;gap:.35rem}
  .s6-step{width:34px;height:34px;border-radius:50%;border:1px solid #ced4da;background:#fff;color:#6c757d;font-weight:600;font-size:.85rem;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:0}
  .s6-step.s6-active{outline:3px solid rgba(13,110,253,.35)}
  .s6-step.s6-partial{background:#fff3cd;border-color:#ffc107;color:#9a7d0a}
  .s6-step.s6-complete{background:#198754;border-color:#198754;color:#fff}
  .s6-btns .btn{font-weight:600}
  .s6-crit{border:1px solid #eef0f2;border-radius:.6rem;padding:.75rem;margin-bottom:.75rem;background:#fff}
  .s6-bottom{position:sticky;bottom:0;background:#fff;border-top:1px solid #dee2e6;padding:.6rem 0;z-index:5}
  @media (max-width:575.98px){ .s6-btns .btn{flex:1 1 46%} }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
  <div>
    <h2 class="mb-1"><i class="fas fa-clipboard-check"></i> Auditoría 6S</h2>
    <div class="text-muted"><?= htmlspecialchars($auditoria['sucursal']) ?> · <?= htmlspecialchars($semanaLabel) ?>
      <?php if ($finalizada): ?><span class="badge bg-success ms-1">Finalizada</span>
      <?php else: ?><span class="badge bg-warning text-dark ms-1">Borrador</span><?php endif; ?>
    </div>
  </div>
  <a href="listar.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list"></i> Volver</a>
</div>

<?php if (($_GET['err'] ?? '') === 'incompleto'): ?>
  <div class="alert alert-warning">Faltan <?= (int)($_GET['faltan'] ?? 0) ?> criterios por resolver (calificar o marcar N.A.). Se guardó como borrador.</div>
<?php elseif (($_GET['msg'] ?? '') === 'guardada'): ?>
  <div class="alert alert-success">Avance guardado.</div>
<?php endif; ?>

<!-- Barra superior: posición + pasos + progreso global -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <strong id="s6Pos">Área 1 de <?= count($depData) ?></strong>
      <span class="text-muted small" id="s6Global">0 de <?= count($depData) ?> áreas completas</span>
    </div>
    <div class="s6-steps">
      <?php foreach ($depData as $i => $d): ?>
        <button type="button" class="s6-step" data-step="<?= $i ?>" onclick="s6Show(<?= $i ?>)" title="<?= htmlspecialchars($d['nombre']) ?>"><?= $i + 1 ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<form method="post" action="guardar.php" enctype="multipart/form-data" id="s6Form">
  <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
  <input type="hidden" name="auditoria_id" value="<?= $id ?>">

  <?php foreach ($depData as $i => $d):
      $depId = $d['id']; ?>
  <section class="dep-panel <?= $i === 0 ? '' : 'd-none' ?>" data-dep-index="<?= $i ?>" data-dep-id="<?= $depId ?>" data-total="<?= $d['count'] ?>">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <h4 class="mb-0"><?= htmlspecialchars($d['nombre']) ?></h4>
          <span class="badge bg-light text-dark border"><?= $d['count'] ?> criterios</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="progress flex-grow-1" style="height:10px;">
            <div class="s6-dep-bar progress-bar bg-secondary" role="progressbar" style="width:0%"></div>
          </div>
          <span class="small text-muted s6-dep-count" style="white-space:nowrap;">0 de <?= $d['count'] ?></span>
        </div>
      </div>
    </div>

    <?php foreach ($d['grupos'] as $g): ?>
      <h6 class="text-primary border-bottom pb-1 mt-3"><?= htmlspecialchars($g['nombre']) ?></h6>
      <?php foreach ($g['items'] as $c):
          $cid = (int)$c['id'];
          $row = $resp[$depId][$cid] ?? null;
          $valActual = '';
          if ($row) {
              if ((int)($row['no_aplica'] ?? 0) === 1) $valActual = 'na';
              elseif ($row['calificacion'] !== null && $row['calificacion'] !== '') $valActual = (string)(int)$row['calificacion'];
          }
      ?>
      <div class="s6-crit">
        <div class="fw-medium mb-2"><?= htmlspecialchars($c['texto']) ?></div>
        <input type="hidden" class="s6-val" name="calif[<?= $depId ?>][<?= $cid ?>]" value="<?= htmlspecialchars($valActual) ?>">
        <div class="s6-btns d-flex flex-wrap gap-2 mb-2">
          <?php foreach ($botones as $val => $b): ?>
            <button type="button" class="btn btn-outline-<?= $b[1] ?> s6-cal <?= $valActual === (string)$val ? 'active' : '' ?>" data-val="<?= $val ?>" onclick="s6SetCal(this)"><?= htmlspecialchars($b[0]) ?></button>
          <?php endforeach; ?>
        </div>

        <!-- Detalles / acción correctiva (colapsable) -->
        <div>
          <button class="btn btn-sm btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#det<?= $depId ?>_<?= $cid ?>">
            <i class="fas fa-sliders-h"></i> Acción correctiva / evidencia
          </button>
          <div class="collapse mt-2" id="det<?= $depId ?>_<?= $cid ?>">
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Prioridad</label>
                <select name="prioridad[<?= $depId ?>][<?= $cid ?>]" class="form-select form-select-sm">
                  <option value="">—</option>
                  <?php foreach (['Urgente','Normal','No urgente'] as $p): ?>
                    <option value="<?= $p ?>" <?= ($row && $row['prioridad'] === $p) ? 'selected' : '' ?>><?= $p ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Días</label>
                <input type="number" min="0" inputmode="numeric" name="dias[<?= $depId ?>][<?= $cid ?>]" class="form-control form-control-sm" placeholder="Días" value="<?= $row && $row['dias_para_corregir'] !== null ? (int)$row['dias_para_corregir'] : '' ?>">
              </div>
              <div class="col-12 col-md-7">
                <label class="form-label small mb-1">Comentarios</label>
                <input type="text" name="coment[<?= $depId ?>][<?= $cid ?>]" class="form-control form-control-sm" placeholder="Comentarios" value="<?= $row ? htmlspecialchars($row['comentarios'] ?? '') : '' ?>">
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Fotos</label>
                <input type="file" name="foto_<?= $depId ?>_<?= $cid ?>[]" class="form-control form-control-sm" accept="image/*" capture="environment" multiple>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="card border-0 shadow-sm my-3">
      <div class="card-body">
        <label class="form-label small mb-1"><i class="fas fa-signature"></i> Firmantes de <?= htmlspecialchars($d['nombre']) ?></label>
        <select name="firmantes[<?= $depId ?>][]" class="form-select" multiple size="3">
          <?php foreach ($d['empleados'] as $e): ?>
            <option value="<?= $e['id'] ?>" <?= in_array((int)$e['id'], $d['firmas'], true) ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted">Mantén presionado (o Ctrl) para elegir varios.</small>
      </div>
    </div>

    <!-- Navegación entre áreas -->
    <div class="d-flex justify-content-between mb-3">
      <button type="button" class="btn btn-outline-secondary" onclick="s6Show(window.s6Idx-1)"><i class="fas fa-chevron-left"></i> Área anterior</button>
      <button type="button" class="btn btn-outline-primary" onclick="s6Show(window.s6Idx+1)">Siguiente área <i class="fas fa-chevron-right"></i></button>
    </div>
  </section>
  <?php endforeach; ?>

  <div class="s6-bottom d-flex flex-wrap gap-2 align-items-center">
    <button type="submit" name="finalizar" value="0" class="btn btn-secondary"><i class="fas fa-save"></i> Guardar avance</button>
    <button type="submit" name="finalizar" value="1" class="btn btn-success" onclick="return confirm('¿Finalizar la auditoría? Deben estar resueltos (calificados o N.A.) todos los criterios de todas las áreas.');"><i class="fas fa-check"></i> Finalizar</button>
    <span class="ms-auto text-muted small">Total de criterios: <?= $total_criterios ?></span>
  </div>
</form>

<script>
(function () {
  const panels = Array.from(document.querySelectorAll('.dep-panel'));
  window.s6Idx = 0;

  window.s6SetCal = function (btn) {
    const crit = btn.closest('.s6-crit');
    const input = crit.querySelector('input.s6-val');
    crit.querySelectorAll('.s6-cal').forEach(b => b.classList.remove('active'));
    // permite "deseleccionar" si tocas el botón ya activo
    if (input.value === btn.dataset.val) {
      input.value = '';
    } else {
      btn.classList.add('active');
      input.value = btn.dataset.val;
    }
    s6Recompute(btn.closest('.dep-panel'));
  };

  function s6Recompute(panel) {
    const total = parseInt(panel.dataset.total, 10) || 0;
    const done = Array.from(panel.querySelectorAll('input.s6-val')).filter(i => i.value !== '').length;
    const pct = total > 0 ? Math.round(done / total * 100) : 0;
    const bar = panel.querySelector('.s6-dep-bar');
    bar.style.width = pct + '%';
    bar.className = 's6-dep-bar progress-bar ' + (done === 0 ? 'bg-secondary' : (done < total ? 'bg-warning' : 'bg-success'));
    panel.querySelector('.s6-dep-count').textContent = done + ' de ' + total;
    const idx = panel.dataset.depIndex;
    const dot = document.querySelector('.s6-step[data-step="' + idx + '"]');
    if (dot) {
      dot.classList.toggle('s6-complete', total > 0 && done === total);
      dot.classList.toggle('s6-partial', done > 0 && done < total);
    }
    s6Global();
  }

  function s6Global() {
    let comp = 0;
    panels.forEach(p => {
      const t = parseInt(p.dataset.total, 10) || 0;
      const d = Array.from(p.querySelectorAll('input.s6-val')).filter(i => i.value !== '').length;
      if (t > 0 && d === t) comp++;
    });
    const g = document.getElementById('s6Global');
    if (g) g.textContent = comp + ' de ' + panels.length + ' áreas completas';
  }

  window.s6Show = function (i) {
    if (i < 0 || i >= panels.length) return;
    window.s6Idx = i;
    panels.forEach((p, k) => p.classList.toggle('d-none', k !== i));
    const pos = document.getElementById('s6Pos');
    if (pos) pos.textContent = 'Área ' + (i + 1) + ' de ' + panels.length;
    document.querySelectorAll('.s6-step').forEach(d => d.classList.toggle('s6-active', parseInt(d.dataset.step, 10) === i));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // init
  panels.forEach(s6Recompute);
  s6Show(0);
})();
</script>

<?php include '../../includes/footer.php'; ?>
