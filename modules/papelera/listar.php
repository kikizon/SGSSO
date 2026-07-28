<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/papelera.php';
exigir('papelera.ver');

// Purga automática de expirados (>30 días) al abrir la papelera
$purgadas_auto = papelera_purgar_expiradas($pdo, 30);

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

$labels = [];
foreach (papelera_registry() as $t => $r) { $labels[$t] = $r['label'] ?? $t; }

$items = $pdo->query("SELECT p.*, u.nombre_completo AS quien
                      FROM papelera p
                      LEFT JOIN usuarios u ON u.id = p.eliminado_por
                      WHERE p.restaurado_en IS NULL
                      ORDER BY p.eliminado_en DESC")->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h2 class="mb-0"><i class="fas fa-trash-restore"></i> Papelera</h2>
  <?php if (!empty($items)): ?>
  <form method="post" action="vaciar.php" onsubmit="return confirm('¿Vaciar la papelera? Se borrarán DEFINITIVAMENTE todos los elementos y sus archivos. Esta acción no se puede deshacer.');">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-dumpster-fire"></i> Vaciar papelera</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-warning"><?= htmlspecialchars($err) ?></div><?php endif; ?>
<?php if ($purgadas_auto > 0): ?><div class="alert alert-info">Se purgaron automáticamente <?= (int)$purgadas_auto ?> elemento(s) con más de 30 días.</div><?php endif; ?>

<div class="alert alert-secondary small">
  Los elementos se conservan aquí al borrarlos. <strong>Restaurar</strong> reintegra el registro con sus datos, hijos y archivos. <strong>Eliminar definitivo</strong> borra también los archivos del disco. La papelera se purga sola a los <strong>30 días</strong>.
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body table-responsive">
    <?php if (empty($items)): ?>
      <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2 d-block opacity-50"></i>La papelera está vacía.</div>
    <?php else: ?>
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr><th>Tipo</th><th>Descripción</th><th>Eliminado por</th><th>Fecha</th><th class="text-end">Acciones</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($labels[$it['tabla']] ?? $it['tabla']) ?></span></td>
          <td><?= htmlspecialchars($it['descripcion'] ?? ('#' . $it['registro_id'])) ?></td>
          <td><?= htmlspecialchars($it['quien'] ?? '—') ?></td>
          <td><?= date('d/m/Y H:i', strtotime($it['eliminado_en'])) ?></td>
          <td class="text-end">
            <form action="restaurar.php" method="post" class="d-inline" onsubmit="return confirm('¿Restaurar este elemento?');">
              <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="btn btn-sm btn-outline-success" title="Restaurar"><i class="fas fa-trash-restore"></i></button>
            </form>
            <form action="purgar.php" method="post" class="d-inline" onsubmit="return confirm('¿Eliminar DEFINITIVAMENTE? Se borrarán también los archivos. No se puede deshacer.');">
              <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" title="Eliminar definitivo"><i class="fas fa-times"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
