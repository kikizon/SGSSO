<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if ($usuario_rol !== 'admin') {
    redirect('modules/auditoria6s/listar.php');
}

$msg = '';
$error = '';

// ----------------- POST (PRG) -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403); exit('Token CSRF inválido.');
    }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear' || $accion === 'editar') {
        $categoria_id = (int)($_POST['categoria_id'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        $deptos = array_map('intval', $_POST['departamentos'] ?? []);

        // Validar categoría
        $okcat = $pdo->prepare("SELECT COUNT(*) FROM categorias_6s WHERE id = ?");
        $okcat->execute([$categoria_id]);
        if (!$categoria_id || !$okcat->fetchColumn()) { $error = 'Categoría inválida.'; }
        elseif ($texto === '') { $error = 'El texto del criterio es obligatorio.'; }

        if (!$error) {
            $pdo->beginTransaction();
            try {
                if ($accion === 'crear') {
                    // Código y orden automáticos
                    $next = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo,2) AS UNSIGNED)),0)+1 FROM criterios_6s")->fetchColumn();
                    do {
                        $codigo = 'C' . str_pad($next, 2, '0', STR_PAD_LEFT);
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM criterios_6s WHERE codigo = ?");
                        $chk->execute([$codigo]); $existe = $chk->fetchColumn();
                        $next++;
                    } while ($existe);

                    $ord = $pdo->prepare("SELECT COALESCE(MAX(orden),0)+1 FROM criterios_6s WHERE categoria_id = ?");
                    $ord->execute([$categoria_id]); $orden = (int)$ord->fetchColumn();

                    $ins = $pdo->prepare("INSERT INTO criterios_6s (codigo, categoria_id, texto, orden, activo) VALUES (?, ?, ?, ?, 1)");
                    $ins->execute([$codigo, $categoria_id, $texto, $orden]);
                    $criterio_id = (int)$pdo->lastInsertId();
                    registrar_auditoria($pdo, $usuario_id, 'INSERT', 'criterios_6s', $criterio_id, json_encode(['texto' => $texto]));
                    $msg = 'Criterio creado.';
                } else {
                    $criterio_id = (int)($_POST['criterio_id'] ?? 0);
                    $up = $pdo->prepare("UPDATE criterios_6s SET categoria_id = ?, texto = ? WHERE id = ?");
                    $up->execute([$categoria_id, $texto, $criterio_id]);
                    registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'criterios_6s', $criterio_id, json_encode(['texto' => $texto]));
                    $msg = 'Criterio actualizado.';
                }

                // Reemplazar asignación de departamentos
                $pdo->prepare("DELETE FROM criterios_6s_departamento WHERE criterio_id = ?")->execute([$criterio_id]);
                if ($deptos) {
                    $insd = $pdo->prepare("INSERT IGNORE INTO criterios_6s_departamento (criterio_id, departamento_id)
                                           SELECT ?, id FROM departamentos WHERE id = ? AND activo = 1");
                    foreach ($deptos as $dep) { $insd->execute([$criterio_id, $dep]); }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'No se pudo guardar el criterio.';
            }
        }
    } elseif ($accion === 'toggle') {
        $cid = (int)($_POST['criterio_id'] ?? 0);
        $pdo->prepare("UPDATE criterios_6s SET activo = 1 - activo WHERE id = ?")->execute([$cid]);
        registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'criterios_6s', $cid, 'toggle activo');
        $msg = 'Estado actualizado.';
    } elseif ($accion === 'eliminar') {
        $cid = (int)($_POST['criterio_id'] ?? 0);
        $uso = $pdo->prepare("SELECT COUNT(*) FROM auditorias_6s_respuestas WHERE criterio_id = ?");
        $uso->execute([$cid]);
        if ($uso->fetchColumn() > 0) {
            // Tiene respuestas: se desactiva (no se borra para no perder historial)
            $pdo->prepare("UPDATE criterios_6s SET activo = 0 WHERE id = ?")->execute([$cid]);
            registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'criterios_6s', $cid, 'desactivado (en uso)');
            $msg = 'El criterio está en auditorías previas, se desactivó en lugar de borrarse.';
        } else {
            $pdo->prepare("DELETE FROM criterios_6s WHERE id = ?")->execute([$cid]); // junction cae por CASCADE
            registrar_auditoria($pdo, $usuario_id, 'DELETE', 'criterios_6s', $cid, null);
            $msg = 'Criterio eliminado.';
        }
    }

    // Redirección PRG con mensaje
    $qs = $error ? ('err=' . urlencode($error)) : ('msg=' . urlencode($msg));
    redirect('modules/auditoria6s/admin_criterios.php?' . $qs);
}

// ----------------- GET -----------------
if (isset($_GET['msg'])) $msg = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];

$categorias = $pdo->query("SELECT id, nombre FROM categorias_6s WHERE activo = 1 ORDER BY orden")->fetchAll();
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Criterios + categoría
$criterios = $pdo->query("SELECT cr.*, cat.nombre AS categoria, cat.orden AS cat_orden
                          FROM criterios_6s cr JOIN categorias_6s cat ON cat.id = cr.categoria_id
                          ORDER BY cat.orden, cr.orden, cr.id")->fetchAll();

// Asignaciones criterio -> [dept_ids]
$asig = [];
foreach ($pdo->query("SELECT criterio_id, departamento_id FROM criterios_6s_departamento")->fetchAll() as $a) {
    $asig[$a['criterio_id']][] = (int)$a['departamento_id'];
}

// Edición
$edit = null;
if (isset($_GET['editar'])) {
    $e = $pdo->prepare("SELECT * FROM criterios_6s WHERE id = ?");
    $e->execute([(int)$_GET['editar']]);
    $edit = $e->fetch();
}
$edit_deptos = $edit ? ($asig[$edit['id']] ?? []) : [];

include '../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
  <h2 class="mb-0"><i class="fas fa-tasks"></i> Criterios 6S</h2>
  <div class="d-flex gap-2">
    <a href="listar.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> Auditorías</a>
  </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card card-body mb-4">
  <h5><?= $edit ? 'Editar criterio' : 'Nuevo criterio' ?></h5>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="accion" value="<?= $edit ? 'editar' : 'crear' ?>">
    <?php if ($edit): ?><input type="hidden" name="criterio_id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="row g-2">
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1">Categoría</label>
        <select name="categoria_id" class="form-select form-select-sm" required>
          <option value="">Seleccione…</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $edit && $edit['categoria_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-9">
        <label class="form-label small mb-1">Texto del criterio</label>
        <input type="text" name="texto" class="form-control form-control-sm" maxlength="255" required
               value="<?= $edit ? htmlspecialchars($edit['texto']) : '' ?>">
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">Aplica a departamentos</label>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach ($departamentos as $d): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="departamentos[]" value="<?= $d['id'] ?>"
                     id="dep<?= $d['id'] ?>" <?= in_array((int)$d['id'], $edit_deptos, true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="dep<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-sm btn-primary"><i class="fas fa-save"></i> <?= $edit ? 'Guardar cambios' : 'Agregar criterio' ?></button>
        <?php if ($edit): ?><a href="admin_criterios.php" class="btn btn-sm btn-outline-secondary">Cancelar</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>

<?php
$cat_actual = null;
foreach ($criterios as $cr):
    if ($cat_actual !== $cr['categoria']):
        if ($cat_actual !== null) echo '</tbody></table></div>';
        $cat_actual = $cr['categoria'];
        ?>
        <h5 class="mt-3"><?= htmlspecialchars($cat_actual) ?></h5>
        <div class="table-responsive"><table class="table table-sm align-middle">
          <thead><tr><th style="width:45%">Criterio</th><th>Departamentos</th><th class="text-center">Estado</th><th class="text-end">Acciones</th></tr></thead>
          <tbody>
    <?php endif; ?>
    <tr class="<?= $cr['activo'] ? '' : 'table-secondary' ?>">
      <td><span class="text-muted small"><?= htmlspecialchars($cr['codigo']) ?></span> <?= htmlspecialchars($cr['texto']) ?></td>
      <td>
        <?php
        $ids = $asig[$cr['id']] ?? [];
        $nombres = [];
        foreach ($departamentos as $d) { if (in_array((int)$d['id'], $ids, true)) $nombres[] = $d['nombre']; }
        if (count($nombres) === count($departamentos) && count($departamentos) > 0) {
            echo '<span class="badge bg-primary">Todos</span>';
        } elseif ($nombres) {
            foreach ($nombres as $n) echo '<span class="badge bg-light text-dark border me-1">' . htmlspecialchars($n) . '</span>';
        } else {
            echo '<span class="text-danger small">Sin asignar</span>';
        }
        ?>
      </td>
      <td class="text-center"><?= $cr['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
      <td class="text-end">
        <a href="admin_criterios.php?editar=<?= $cr['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
        <form method="post" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
          <input type="hidden" name="accion" value="toggle">
          <input type="hidden" name="criterio_id" value="<?= $cr['id'] ?>">
          <button class="btn btn-sm btn-outline-info" title="Activar/Desactivar"><i class="fas fa-power-off"></i></button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este criterio? Si está en auditorías previas solo se desactivará.');">
          <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
          <input type="hidden" name="accion" value="eliminar">
          <input type="hidden" name="criterio_id" value="<?= $cr['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
        </form>
      </td>
    </tr>
<?php endforeach;
if ($cat_actual !== null) echo '</tbody></table></div>'; ?>

<?php include '../../includes/footer.php'; ?>