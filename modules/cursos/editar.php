<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM cursos WHERE id = ?");
$stmt->execute([$id]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: listar.php');
    exit;
}

// Obtener asignaciones actuales
$asignaciones = $pdo->prepare("SELECT * FROM curso_asignaciones WHERE curso_id = ?");
$asignaciones->execute([$id]);
$asigs = $asignaciones->fetchAll();

$tipo_actual = 'todos';
$entidades_seleccionadas = [];
$obligatorio = 0;
if (!empty($asigs)) {
    $tipo_actual = $asigs[0]['tipo_asignacion'];
    $obligatorio = $asigs[0]['obligatorio'];
    if ($tipo_actual !== 'todos') {
        $entidades_seleccionadas = array_column($asigs, 'entidad_id');
    }
}

$sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $curso_sucursal_id = ($_POST['curso_sucursal_id'] ?? '') !== '' ? (int)$_POST['curso_sucursal_id'] : null;
    $vigencia_meses = ($_POST['vigencia_meses'] ?? '') !== '' ? max(1, (int)$_POST['vigencia_meses']) : null;
    $tipo_asignacion = $_POST['tipo_asignacion'] ?? 'todos';
    if (!in_array($tipo_asignacion, ['todos','sucursal','departamento','empleado','excepto_empleado'], true)) { $tipo_asignacion = 'todos'; }
    $entidades = $_POST['entidades'] ?? [];
    $obligatorio = isset($_POST['obligatorio']) ? 1 : 0;

    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE cursos SET nombre = ?, descripcion = ?, sucursal_id = ?, vigencia_meses = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion, $curso_sucursal_id, $vigencia_meses, $id]);

            // Eliminar asignaciones anteriores
            $pdo->prepare("DELETE FROM curso_asignaciones WHERE curso_id = ?")->execute([$id]);

            // Insertar nuevas asignaciones
            if ($tipo_asignacion === 'todos') {
                $stmt = $pdo->prepare("INSERT INTO curso_asignaciones (curso_id, tipo_asignacion, obligatorio) VALUES (?, 'todos', ?)");
                $stmt->execute([$id, $obligatorio]);
            } else {
                foreach ($entidades as $entidad_id) {
                    if (empty($entidad_id)) continue;
                    $stmt = $pdo->prepare("INSERT INTO curso_asignaciones (curso_id, tipo_asignacion, entidad_id, obligatorio) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id, $tipo_asignacion, $entidad_id, $obligatorio]);
                }
            }

            $pdo->commit();
            $success = 'Curso/formato actualizado.';
            // Recargar asignaciones
            $asignaciones->execute([$id]);
            $asigs = $asignaciones->fetchAll();
            $tipo_actual = $asigs[0]['tipo_asignacion'] ?? 'todos';
            $obligatorio = $asigs[0]['obligatorio'] ?? 0;
            $entidades_seleccionadas = array_column($asigs, 'entidad_id');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-edit"></i> Editar Curso/formato</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post" id="formCurso">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Información básica</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($curso['nombre']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Descripción (opcional)</label>
                        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($curso['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="obligatorio" id="obligatorio" class="form-check-input" value="1" <?= $obligatorio ? 'checked' : '' ?>>
                        <label for="obligatorio" class="form-check-label">Curso/formato obligatorio?</label>
                    </div>
                    <div class="mb-3">
                        <label>Sucursal del curso</label>
                        <select name="curso_sucursal_id" class="form-select">
                            <option value="">Todas las sucursales</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ((string)($curso['sucursal_id'] ?? '') === (string)$s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">El curso aplica solo a esa sucursal. El "Alcance" de la derecha refina <em>dentro</em> de ella.</small>
                    </div>
                    <div class="mb-3">
                       <label>Vigencia (meses)</label>
                       <input type="number" name="vigencia_meses" class="form-control" min="1"
                              placeholder="Vacío = no vence"
                              value="<?= htmlspecialchars($_POST['vigencia_meses'] ?? $curso['vigencia_meses'] ?? '') ?>">
                       <small class="text-muted">Si el curso/formato caduca, indica cada cuántos meses debe renovarse. Déjalo vacío si no vence.</small>
                   </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Alcance del curso/formato</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Aplica a:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="todos" id="todos" <?= $tipo_actual === 'todos' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="todos">Todos los empleados</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="sucursal" id="sucursal" <?= $tipo_actual === 'sucursal' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sucursal">Sucursales específicas</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="departamento" id="departamento" <?= $tipo_actual === 'departamento' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="departamento">Departamentos específicos</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="empleado" id="empleado" <?= $tipo_actual === 'empleado' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="empleado">Empleados específicos</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="excepto_empleado" id="excepto_empleado" <?= $tipo_actual === 'excepto_empleado' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="excepto_empleado">Todos, excepto empleados específicos</label>
                        </div>
                    </div>

                    <div id="panelSucursales" style="display:<?= $tipo_actual === 'sucursal' ? 'block' : 'none' ?>;">
                        <label class="form-label">Seleccione sucursales:</label>
                        <div style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                            <?php foreach ($sucursales as $s): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="entidades[]" value="<?= $s['id'] ?>" <?= in_array($s['id'], $entidades_seleccionadas) ? 'checked' : '' ?>>
                                    <label class="form-check-label"><?= htmlspecialchars($s['nombre']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="panelDepartamentos" style="display:<?= $tipo_actual === 'departamento' ? 'block' : 'none' ?>;">
                        <label class="form-label">Seleccione departamentos:</label>
                        <div style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                            <?php foreach ($departamentos as $d): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="entidades[]" value="<?= $d['id'] ?>" <?= in_array($d['id'], $entidades_seleccionadas) ? 'checked' : '' ?>>
                                    <label class="form-check-label"><?= htmlspecialchars($d['nombre']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="panelEmpleados" style="display:<?= in_array($tipo_actual, ['empleado','excepto_empleado'], true) ? 'block' : 'none' ?>;">
                        <label class="form-label">Buscar empleado:</label>
                        <input type="text" class="form-control mb-2" id="buscarEmpleado" placeholder="Escriba número o nombre..." list="empleados-datalist" autocomplete="off">
                        <datalist id="empleados-datalist"></datalist>
                        <div id="listaEmpleadosSeleccionados" style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                            <!-- Se llena con JS -->
                        </div>
                        <input type="hidden" name="empleados_seleccionados" id="empleadosSeleccionados">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Actualizar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</div>

<script>
// Control de paneles
document.querySelectorAll('input[name="tipo_asignacion"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('panelSucursales').style.display = this.value === 'sucursal' ? 'block' : 'none';
        document.getElementById('panelDepartamentos').style.display = this.value === 'departamento' ? 'block' : 'none';
        document.getElementById('panelEmpleados').style.display = (this.value === 'empleado' || this.value === 'excepto_empleado') ? 'block' : 'none';
    });
});

// Lógica de empleados
let empleadosSeleccionados = <?= json_encode(array_map(function($id) use ($pdo) {
    $stmt = $pdo->prepare("SELECT id, numero_empleado, nombre FROM empleados WHERE id = ?");
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    return ['id' => $id, 'texto' => $e['numero_empleado'] . ' - ' . $e['nombre']];
}, $entidades_seleccionadas)) ?>;

const datalist = document.getElementById('empleados-datalist');
const buscar = document.getElementById('buscarEmpleado');
const listaDiv = document.getElementById('listaEmpleadosSeleccionados');

const todosEmpleados = <?= json_encode($pdo->query("SELECT id, numero_empleado, nombre FROM empleados WHERE activo = 1 ORDER BY nombre")->fetchAll()) ?>;
todosEmpleados.forEach(emp => {
    const option = document.createElement('option');
    option.value = `${emp.numero_empleado} - ${emp.nombre}`;
    option.dataset.id = emp.id;
    datalist.appendChild(option);
});

function actualizarListaEmpleados() {
    listaDiv.innerHTML = '';
    empleadosSeleccionados.forEach(emp => {
        const item = document.createElement('div');
        item.className = 'd-flex justify-content-between align-items-center border-bottom py-1';
        item.innerHTML = `<span>${emp.texto}</span>
                         <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarEmpleado(${emp.id})"><i class="fas fa-times"></i></button>`;
        listaDiv.appendChild(item);
    });
    // Refrescar checkboxes ocultos
    const oldCheckboxes = document.querySelectorAll('input[name="entidades[]"][data-empleado]');
    oldCheckboxes.forEach(cb => cb.remove());
    empleadosSeleccionados.forEach(e => {
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = 'entidades[]';
        cb.value = e.id;
        cb.checked = true;
        cb.style.display = 'none';
        cb.dataset.empleado = 'true';
        document.getElementById('formCurso').appendChild(cb);
    });
}

buscar.addEventListener('change', function() {
    const selectedOption = Array.from(datalist.options).find(opt => opt.value === this.value);
    if (selectedOption) {
        const id = selectedOption.dataset.id;
        const texto = selectedOption.value;
        if (!empleadosSeleccionados.some(e => e.id == id)) {
            empleadosSeleccionados.push({id, texto});
            actualizarListaEmpleados();
        }
        this.value = '';
    }
});

window.eliminarEmpleado = function(id) {
    empleadosSeleccionados = empleadosSeleccionados.filter(e => e.id != id);
    actualizarListaEmpleados();
};

// Inicializar
actualizarListaEmpleados();
</script>

<?php include '../../includes/footer.php'; ?>