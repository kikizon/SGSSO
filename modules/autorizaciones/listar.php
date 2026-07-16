<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/authorization.php';

$es_admin = autz_puede_autorizar($usuario_rol);

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Filtro de estado (admin). Por defecto: pendientes.
$f_estado = $_GET['estado'] ?? 'pendiente';
$estados_validos = ['pendiente','aprobada','rechazada','cancelada','todas'];
if (!in_array($f_estado, $estados_validos, true)) { $f_estado = 'pendiente'; }

$where = [];
$params = [];

if ($es_admin) {
    // El admin ve todo el sistema
    if ($f_estado !== 'todas') { $where[] = 's.estado = ?'; $params[] = $f_estado; }
} else {
    // Un solicitante solo ve sus propias solicitudes
    $where[] = 's.solicitante_id = ?';
    $params[] = $usuario_id;
    if ($f_estado !== 'todas') { $where[] = 's.estado = ?'; $params[] = $f_estado; }
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT s.*, u.nombre_completo AS solicitante, suc.nombre AS sucursal,
               ap.nombre_completo AS aprobador
        FROM solicitudes_autorizacion s
        JOIN usuarios u ON u.id = s.solicitante_id
        LEFT JOIN sucursales suc ON suc.id = s.sucursal_id
        LEFT JOIN usuarios ap ON ap.id = s.aprobador_id
        $where_sql
        ORDER BY (s.estado = 'pendiente') DESC, s.creado_en DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$solicitudes = $st->fetchAll();

$cfg = autz_config();

$titulo_accion = ['UPDATE' => 'Modificación', 'DELETE' => 'Eliminación'];
$badge_estado = [
    'pendiente' => 'warning text-dark',
    'aprobada'  => 'success',
    'rechazada' => 'danger',
    'cancelada' => 'secondary',
];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h2 class="mb-0"><i class="fas fa-user-shield"></i> Autorizaciones</h2>
  <form method="get" class="d-flex gap-2">
    <select name="estado" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
      <option value="pendiente" <?= $f_estado==='pendiente'?'selected':'' ?>>Pendientes</option>
      <option value="aprobada"  <?= $f_estado==='aprobada'?'selected':'' ?>>Aprobadas</option>
      <option value="rechazada" <?= $f_estado==='rechazada'?'selected':'' ?>>Rechazadas</option>
      <option value="todas"     <?= $f_estado==='todas'?'selected':'' ?>>Todas</option>
    </select>
  </form>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<p class="text-muted small">
  <?php if ($es_admin): ?>
    Solicitudes de modificación o eliminación enviadas por usuarios y supervisores. Como administrador puedes aprobarlas o rechazarlas.
  <?php else: ?>
    Estado de las solicitudes que has enviado. Un administrador debe aprobarlas para que se apliquen.
  <?php endif; ?>
</p>

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead>
      <tr>
        <th>Tipo</th><th>Registro</th><th>Solicitante</th><th>Sucursal</th>
        <th>Fecha</th><th class="text-center">Estado</th>
        <?php if ($es_admin): ?><th class="text-end">Acciones</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($solicitudes)): ?>
        <tr><td colspan="<?= $es_admin ? 7 : 6 ?>" class="text-center text-muted py-4">Sin solicitudes.</td></tr>
      <?php else: foreach ($solicitudes as $s):
        $label_tabla = $cfg[$s['tabla']]['label'] ?? $s['tabla'];
      ?>
        <tr>
          <td>
            <span class="badge bg-<?= $s['accion']==='DELETE'?'danger':'info' ?>">
              <?= $titulo_accion[$s['accion']] ?? $s['accion'] ?>
            </span>
          </td>
          <td>
            <strong><?= htmlspecialchars($label_tabla) ?></strong> #<?= (int)$s['registro_id'] ?>
            <?php if (!empty($s['descripcion'])): ?>
              <div class="text-muted small"><?= htmlspecialchars($s['descripcion']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($s['solicitante']) ?></td>
          <td><?= htmlspecialchars($s['sucursal'] ?? '—') ?></td>
          <td class="small"><?= format_datetime_es($s['creado_en']) ?></td>
          <td class="text-center">
            <span class="badge bg-<?= $badge_estado[$s['estado']] ?? 'secondary' ?>"><?= ucfirst($s['estado']) ?></span>
            <?php if ($s['estado']==='rechazada' && $s['motivo']): ?>
              <div class="text-muted small fst-italic">“<?= htmlspecialchars($s['motivo']) ?>”</div>
            <?php elseif ($s['estado'] !== 'pendiente' && $s['aprobador']): ?>
              <div class="text-muted small">por <?= htmlspecialchars($s['aprobador']) ?></div>
            <?php endif; ?>
          </td>
          <?php if ($es_admin): ?>
          <td class="text-end">
            <?php if ($s['estado']==='pendiente'): ?>
              <form method="post" action="procesar.php" class="d-inline"
                    onsubmit="return confirm('¿Aprobar y aplicar esta acción? No se puede deshacer.');">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="accion" value="aprobar">
                <input type="hidden" name="solicitud_id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Aprobar</button>
              </form>
              <button type="button" class="btn btn-sm btn-outline-danger"
                      data-bs-toggle="modal" data-bs-target="#rechazarModal"
                      data-id="<?= (int)$s['id'] ?>">
                <i class="fas fa-times"></i> Rechazar
              </button>
            <?php else: ?>
              <span class="text-muted small"><?= format_datetime_es($s['resuelto_en']) ?></span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if ($es_admin): ?>
<!-- Modal de rechazo -->
<div class="modal fade" id="rechazarModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="procesar.php" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
      <input type="hidden" name="accion" value="rechazar">
      <input type="hidden" name="solicitud_id" id="rechazar_id">
      <div class="modal-header">
        <h5 class="modal-title">Rechazar solicitud</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Motivo del rechazo <span class="text-danger">*</span></label>
        <textarea name="motivo" class="form-control" rows="3" required
                  placeholder="Explica por qué se rechaza la solicitud…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Rechazar</button>
      </div>
    </form>
  </div>
</div>
<script>
const rechazarModal = document.getElementById('rechazarModal');
rechazarModal.addEventListener('show.bs.modal', e => {
  document.getElementById('rechazar_id').value = e.relatedTarget.getAttribute('data-id');
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
