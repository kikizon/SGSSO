<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$error = $success = '';

// Obtener catálogos para los selectores
$sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();
$departamentos = $pdo->query("SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { $error = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.'; }
else {
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
            $stmt = $pdo->prepare("INSERT INTO cursos (nombre, descripcion, sucursal_id, vigencia_meses) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $curso_sucursal_id, $vigencia_meses]);
            $curso_id = $pdo->lastInsertId();

            // Guardar asignaciones
            if ($tipo_asignacion === 'todos') {
                $stmt = $pdo->prepare("INSERT INTO curso_asignaciones (curso_id, tipo_asignacion, obligatorio) VALUES (?, 'todos', ?)");
                $stmt->execute([$curso_id, $obligatorio]);
            } else {
                foreach ($entidades as $entidad_id) {
                    if (empty($entidad_id)) continue;
                    $stmt = $pdo->prepare("INSERT INTO curso_asignaciones (curso_id, tipo_asignacion, entidad_id, obligatorio) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$curso_id, $tipo_asignacion, $entidad_id, $obligatorio]);
                }
            }

            $pdo->commit();
            $success = 'Curso/Formato creado exitosamente.';
            $_POST = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error = 'El nombre ya existe.';
            } else {
                error_log($e->getMessage()); $error = 'Ocurrió un error. Intenta de nuevo.';
            }
        }
    }

}}

include '../../includes/header.php';
?>

<h2><i class="fas fa-plus-circle"></i> Nuevo Curso/Formato</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php">Ver listado</a></div><?php endif; ?>

<form method="post" id="formCurso">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Información básica</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Descripción (opcional)</label>
                        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="obligatorio" id="obligatorio" class="form-check-input" value="1" <?= isset($_POST['obligatorio']) ? 'checked' : '' ?>>
                        <label for="obligatorio" class="form-check-label">Curso/formato obligatorio?</label>
                    </div>
                    <div class="mb-3">
                        <label>Sucursal del curso</label>
                        <select name="curso_sucursal_id" class="form-select">
                            <option value="">Todas las sucursales</option>
                            <?php foreach ($sucursales as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (($_POST['curso_sucursal_id'] ?? '') == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">El curso aplica solo a esa sucursal. El "Alcance" de la derecha refina <em>dentro</em> de ella (p. ej. "todos excepto" abarca solo esa sucursal).</small>
                    </div>
                    <div class="mb-3">
                       <label>Vigencia (meses)</label>
                       <input type="number" name="vigencia_meses" class="form-control" min="1"
                              placeholder="Vacío = no vence"
                              value="<?= htmlspecialchars($_POST['vigencia_meses'] ?? '') ?>">
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
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="todos" id="todos" checked>
                            <label class="form-check-label" for="todos">Todos los empleados</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="sucursal" id="sucursal">
                            <label class="form-check-label" for="sucursal">Sucursales específicas</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="departamento" id="departamento">
                            <label class="form-check-label" for="departamento">Departamentos específicos</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="empleado" id="empleado">
                            <label class="form-check-label" for="empleado">Empleados específicos</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipo_asignacion" value="excepto_empleado" id="excepto_empleado">
                            <label class="form-check-label" for="excepto_empleado">Todos, excepto empleados específicos</label>
                        </div>
                    </div>

                    <div id="panelSucursales" style="display:none;">
                        <label class="form-label">Seleccione sucursales:</label>
                        <div style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                            <?php foreach ($sucursales as $s): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="entidades[]" value="<?= $s['id'] ?>">
                                    <label class="form-check-label"><?= htmlspecialchars($s['nombre']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="panelDepartamentos" style="display:none;">
                        <label class="form-label">Seleccione departamentos:</label>
                        <div style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                            <?php foreach ($departamentos as $d): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="entidades[]" value="<?= $d['id'] ?>">
                                    <label class="form-check-label"><?= htmlspecialchars($d['nombre']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="panelEmpleados" style="display:none;">
                        <label class="form-label">Buscar empleado:</label>
                        <input type="text" class="form-control mb-2" id="buscarEmpleado" placeholder="Escriba número o nombre..." list="empleados-datalist" autocomplete="off">
                        <datalist id="empleados-datalist"></datalist>
                        <div id="listaEmpleadosSeleccionados" style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                            <!-- Aquí se irán agregando los empleados seleccionados -->
                        </div>
                        <input type="hidden" name="empleados_seleccionados" id="empleadosSeleccionados">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="listar.php" class="btn btn-secondary">Cancelar</a>
</form>

<script>
// Cambiar panel según tipo de asignación
document.querySelectorAll('input[name="tipo_asignacion"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('panelSucursales').style.display = this.value === 'sucursal' ? 'block' : 'none';
        document.getElementById('panelDepartamentos').style.display = this.value === 'departamento' ? 'block' : 'none';
        document.getElementById('panelEmpleados').style.display = (this.value === 'empleado' || this.value === 'excepto_empleado') ? 'block' : 'none';
    });
});

// Lógica para empleados (búsqueda y selección)
let empleadosSeleccionados = [];
const datalist = document.getElementById('empleados-datalist');
const buscar = document.getElementById('buscarEmpleado');
const listaDiv = document.getElementById('listaEmpleadosSeleccionados');

// Cargar todos los empleados para el datalist (podría ser una consulta AJAX, pero para simplificar usamos una variable PHP)
const todosEmpleados = <?= json_encode($pdo->query("SELECT id, numero_empleado, nombre FROM empleados WHERE activo = 1 ORDER BY nombre")->fetchAll()) ?>;
todosEmpleados.forEach(emp => {
    const option = document.createElement('option');
    option.value = `${emp.numero_empleado} - ${emp.nombre}`;
    option.dataset.id = emp.id;
    datalist.appendChild(option);
});

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

function actualizarListaEmpleados() {
    listaDiv.innerHTML = '';
    empleadosSeleccionados.forEach(emp => {
        const item = document.createElement('div');
        item.className = 'd-flex justify-content-between align-items-center border-bottom py-1';
        item.innerHTML = `<span>${emp.texto}</span>
                         <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarEmpleado(${emp.id})"><i class="fas fa-times"></i></button>`;
        listaDiv.appendChild(item);
    });
    // Actualizar los checkboxes ocultos para enviar al servidor
    const checkboxes = empleadosSeleccionados.map(e => `<input type="checkbox" name="entidades[]" value="${e.id}" checked style="display:none;">`).join('');
    document.getElementById('empleadosSeleccionados').insertAdjacentHTML('afterend', checkboxes);
}

window.eliminarEmpleado = function(id) {
    empleadosSeleccionados = empleadosSeleccionados.filter(e => e.id != id);
    actualizarListaEmpleados();
};
</script>

<?php include '../../includes/footer.php'; ?>