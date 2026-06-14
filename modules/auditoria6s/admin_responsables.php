<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if ($usuario_rol !== 'admin') {
    redirect('modules/auditoria6s/listar.php');
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403); exit('Token CSRF inválido.');
    }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $departamento_id = $_POST['departamento_id'] !== '' ? (int)$_POST['departamento_id'] : null;
        if ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } else {
            if ($accion === 'crear') {
                $pdo->prepare("INSERT INTO responsables_6s (departamento_id, nombre, activo) VALUES (?, ?, 1)")
                    ->execute([$departamento_id, $nombre]);
                $rid = (int)$pdo->lastInsertId();
                registrar_auditoria($pdo, $usuario_id, 'INSERT', 'responsables_6s', $rid, json_encode(['nombre' => $nombre]));
                $msg = 'Responsable agregado.';
            } else {
                $rid = (int)($_POST['responsable_id'] ?? 0);
                $pdo->prepare("UPDATE responsables_6s SET departamento_id = ?, nombre = ? WHERE id = ?")
                    ->execute([$departamento_id, $nombre, $rid]);
                registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'responsables_6s', $rid, json_encode(['nombre' => $nombre]));
                $msg = 'Responsable actualizado.';
            }
        }
    } elseif ($accion === 'toggle') {
        $rid = (int)($_POST['responsable_id'] ?? 0);
        $pdo->prepare("UPDATE responsables_6s SET activo = 1 - activo WHERE id = ?")->execute([$rid]);
        $msg = 'Estado actualizado.';
    } elseif ($accion === 'eliminar') {
        $rid = (int)($_POST['responsable_id'] ?? 0);
        // FK en respuestas es ON DELETE SET NULL: borrar es seguro
        $pdo->prepare("DELETE FROM responsables_6s WHERE id = ?")->execute([$rid]);
        registrar_auditoria($pdo, $usuario_id, 'DELETE', 'responsables_6s', $rid, null);
        $msg = 'Responsable eliminado.';
    }

    $qs = $error ? ('err=' . urlencode($error)) : ('msg=' . urlencode($msg));
    redirect('modules/auditoria6s/admin_responsables.php?' . $qs);
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];

$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll();
$responsables = $pdo->query("SELECT r.*, d.nombre AS departamento
                             FROM responsables_6s r LEFT JOIN departamentos d ON d.id = r.departamento_id
                             ORDER BY d.nombre, r.nombre")->fetchAll();

$edit = null;
if (isset($_GET['editar'])) {
    $e = $pdo->prepare("SELECT * FROM responsables_6s WHERE id = ?");
    $e->execute([(int)$_GET['editar']]);
    $edit = $e->fetch();
}

include '../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h2 class="mb-0"><i class="fas fa-user-tag"></i> Responsables 6S</h2>
  <a href="admin_criterios.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-tasks"></i> Criterios</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
  <div class="col-12 col-md-4">
    <div class="card card-body mb-3">
      <h6><?= $edit ? 'Editar responsable' : 'Nuevo responsable' ?></h6>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="accion" value="<?= $edit ? 'editar' : 'crear' ?>">
        <?php if ($edit): ?><input type="hidden" name="responsable_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="mb-2">
          <label class="form-label small mb-1">Nombre</label>
          <input type="text" name="nombre" class="form-control form-control-sm" maxlength="100" required
                 value="<?= $edit ? htmlspecialchars($edit['nombre']) : '' ?>">
        </div>
        <div class="mb-2">
          <label class="form-label small mb-1">Departamento</label>
          <select name="departamento_id" class="form-select form-select-sm">
            <option value="">— Sin departamento (global) —</option>
            <?php foreach ($departamentos as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $edit && $edit['departamento_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-sm btn-primary w-100"><i class="fas fa-save"></i> <?= $edit ? 'Guardar' : 'Agregar' ?></button>
        <?php if ($edit): ?><a href="admin_responsables.php" class="btn btn-sm btn-link w-100">Cancelar</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-12 col-md-8">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Nombre</th><th>Departamento</th><th class="text-center">Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <?php if (empty($responsables)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">Sin responsables.</td></tr>
          <?php else: foreach ($responsables as $r): ?>
            <tr class="<?= $r['activo'] ? '' : 'table-secondary' ?>">
              <td><?= htmlspecialchars($r['nombre']) ?></td>
              <td><?= $r['departamento'] ? htmlspecialchars($r['departamento']) : '<span class="text-muted">Global</span>' ?></td>
              <td class="text-center"><?= $r['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
              <td class="text-end">
                <a href="admin_responsables.php?editar=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen"></i></a>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                  <input type="hidden" name="accion" value="toggle">
                  <input type="hidden" name="responsable_id" value="<?= $r['id'] ?>">
                  <button class="btn btn-sm btn-outline-info"><i class="fas fa-power-off"></i></button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar responsable? Quedará desvinculado de las respuestas previas.');">
                  <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="responsable_id" value="<?= $r['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>