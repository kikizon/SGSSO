<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$estado = $_GET['estado'] ?? '1';
$whereEstado = ''; $params = [];
if ($estado !== '') { $whereEstado = "WHERE activo = ?"; $params[] = $estado; }

$sql = "SELECT * FROM departamentos $whereEstado ORDER BY nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$departamentos = $stmt->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-building"></i> Gestión de Departamentos</h2>
<a href="crear.php" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Nuevo Departamento</a>

<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
<?php if (!empty($_GET['err'])): ?><div class="alert alert-warning"><?= htmlspecialchars($_GET['err']) ?></div><?php endif; ?>

<!-- Filtro de estado (form GET, separado del form de borrado) -->
<div class="d-flex justify-content-end mb-3">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-auto"><label class="col-form-label">Estado:</label></div>
        <div class="col-auto">
            <select name="estado" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Inactivos</option>
                <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <div class="col-auto"><a href="listar.php" class="btn btn-outline-secondary btn-sm">Limpiar</a></div>
    </form>
</div>

<form method="post" action="eliminar_bloque.php" id="formBloque"
      onsubmit="return confirmarBloque();">
  <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

  <!-- Barra de acción en bloque -->
  <div class="d-flex align-items-center gap-2 mb-2">
    <button type="submit" class="btn btn-danger btn-sm" id="btnBloque" disabled>
      <i class="fas fa-trash"></i> Eliminar/Desactivar seleccionados (<span id="contSel">0</span>)
    </button>
    <span class="text-muted small">Si un departamento tiene empleados u otras dependencias, se <strong>desactiva</strong> en vez de borrarse.</span>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th style="width:36px;"><input type="checkbox" id="checkTodos" title="Seleccionar todos"></th>
                <th>Nombre</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($departamentos as $d): ?>
            <tr>
                <td><input type="checkbox" class="chk-dep" name="ids[]" value="<?= $d['id'] ?>"></td>
                <td><?= htmlspecialchars($d['nombre']) ?></td>
                <td><?= $d['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
                <td>
                    <a href="editar.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                    <a href="eliminar.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este departamento?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($departamentos)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">Sin departamentos con ese filtro.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
  </div>
</form>

<script>
(function () {
  const checkTodos = document.getElementById('checkTodos');
  const chks = () => Array.from(document.querySelectorAll('.chk-dep'));
  const btn = document.getElementById('btnBloque');
  const cont = document.getElementById('contSel');

  function actualizar() {
    const sel = chks().filter(c => c.checked).length;
    cont.textContent = sel;
    btn.disabled = sel === 0;
    if (checkTodos) {
      const total = chks().length;
      checkTodos.checked = sel > 0 && sel === total;
      checkTodos.indeterminate = sel > 0 && sel < total;
    }
  }
  if (checkTodos) checkTodos.addEventListener('change', function () { chks().forEach(c => c.checked = this.checked); actualizar(); });
  chks().forEach(c => c.addEventListener('change', actualizar));
  window.confirmarBloque = function () {
    const sel = chks().filter(c => c.checked).length;
    if (sel === 0) return false;
    return confirm('¿Eliminar o desactivar ' + sel + ' departamento(s) seleccionados?');
  };
  actualizar();
})();
</script>

<?php include '../../includes/footer.php'; ?>
