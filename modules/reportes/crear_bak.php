<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$tipo_seleccionado = $_GET['tipo'] ?? 'acto_inseguro';
if (!in_array($tipo_seleccionado, ['acto_inseguro', 'accidente'])) {
    $tipo_seleccionado = 'acto_inseguro';
}

// Catálogos según tipo
if ($tipo_seleccionado == 'acto_inseguro') {
    $catalogo = $pdo->query("SELECT id, descripcion FROM actos_inseguros WHERE activo = 1 ORDER BY descripcion")->fetchAll();
    $labelCatalogo = 'Acto Inseguro';
} else {
    $catalogo = $pdo->query("SELECT id, descripcion FROM tipos_accidente WHERE activo = 1 ORDER BY descripcion")->fetchAll();
    $labelCatalogo = 'Tipo de Accidente';
}

$atenciones = $pdo->query("SELECT id, descripcion FROM atenciones_medicas WHERE activo = 1 ORDER BY descripcion")->fetchAll();

$error = '';
$success = '';
$warnings = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? 'acto_inseguro';
    $empleado_id = $_POST['empleado_id'] ?? '';
    $departamento_id = $_POST['departamento_id'] ?? '';
    $sucursal_id = $_POST['sucursal_id'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $catalogo_id = $_POST['catalogo_id'] ?? '';
    $gravedad = !empty($_POST['gravedad']) ? $_POST['gravedad'] : null;
    $atencion_medica_id = $_POST['atencion_medica_id'] ?? null;
    $observacion = trim($_POST['observacion'] ?? '');
    $dias_perdidos = (int)($_POST['dias_perdidos'] ?? 0);
    $st7 = isset($_POST['st7']) ? 1 : 0;
    $costo_atencion = !empty($_POST['costo_atencion']) ? (float)$_POST['costo_atencion'] : null;

    if (!$empleado_id || !$departamento_id || !$sucursal_id || !$fecha || !$hora || !$catalogo_id) {
        $error = 'Todos los campos obligatorios deben completarse.';
    } else {
        $pdo->beginTransaction();
        try {
            $acto_id = ($tipo == 'acto_inseguro') ? $catalogo_id : null;
            $accidente_id = ($tipo == 'accidente') ? $catalogo_id : null;
            $atencion_id = ($tipo == 'accidente' && $atencion_medica_id) ? $atencion_medica_id : null;

            $stmt = $pdo->prepare("INSERT INTO reportes (empleado_id, departamento_id, sucursal_id, fecha, hora, acto_inseguro_id, accidente_id, gravedad, atencion_medica_id, observacion, dias_perdidos, st7, costo_atencion, tipo, reportado_por)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empleado_id, $departamento_id, $sucursal_id, $fecha, $hora, $acto_id, $accidente_id, $gravedad, $atencion_id, $observacion, $dias_perdidos, $st7, $costo_atencion, $tipo, $usuario_id]);
            $reporte_id = $pdo->lastInsertId();

            // Procesar múltiples archivos de evidencias
            $archivos_subidos = 0;
            if (!empty($_FILES['evidencias']['name'][0])) {
                $total_archivos = count($_FILES['evidencias']['name']);
                for ($i = 0; $i < $total_archivos; $i++) {
                    $nombre_original = $_FILES['evidencias']['name'][$i];
                    if ($_FILES['evidencias']['error'][$i] !== UPLOAD_ERR_OK) {
                        $warnings[] = "Error al subir '$nombre_original': código " . $_FILES['evidencias']['error'][$i];
                        continue;
                    }

                    $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
                    if (!in_array($ext, $allowed)) {
                        $warnings[] = "Formato no permitido: '$nombre_original'";
                        continue;
                    }

                    $tamanio = $_FILES['evidencias']['size'][$i];
                    if ($tamanio > 10 * 1024 * 1024) {
                        $warnings[] = "Archivo demasiado grande (máx 10 MB): '$nombre_original'";
                        continue;
                    }

                    $tipo_archivo = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'imagen' : 'documento';
                    $nombre_archivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $nombre_original);
                    $destino = UPLOAD_DIR . $nombre_archivo;

                    if (!is_dir(UPLOAD_DIR)) {
                        if (!mkdir(UPLOAD_DIR, 0755, true)) {
                            $warnings[] = "No se pudo crear el directorio de subidas. Verifique permisos.";
                            continue;
                        }
                    }

                    if (!is_writable(UPLOAD_DIR)) {
                        $warnings[] = "El directorio de subidas no tiene permisos de escritura.";
                        continue;
                    }

                    if (move_uploaded_file($_FILES['evidencias']['tmp_name'][$i], $destino)) {
                        $stmt = $pdo->prepare("INSERT INTO reportes_evidencias (reporte_id, nombre_archivo, tipo) VALUES (?, ?, ?)");
                        $stmt->execute([$reporte_id, $nombre_archivo, $tipo_archivo]);
                        $archivos_subidos++;
                    } else {
                        $warnings[] = "No se pudo mover el archivo: '$nombre_original'";
                    }
                }
            }

            $pdo->commit();
            
            // Auditoría
            $detalles = json_encode([
                'tipo'          => $tipo,
                'empleado_id'   => $empleado_id,
                'fecha'         => $fecha,
                'catalogo_id'   => $catalogo_id,
                'gravedad'      => $gravedad,
                'dias_perdidos' => $dias_perdidos
            ]);
            registrar_auditoria($pdo, $usuario_id, 'INSERT', 'reportes', $reporte_id, $detalles);
            
            $success = "Reporte creado exitosamente. Evidencias subidas: $archivos_subidos.";
            $_POST = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al guardar el reporte: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<h2><i class="fas fa-plus-circle"></i> Nuevo Reporte</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="listar.php?tipo=<?= $tipo_seleccionado ?>">Ver listado</a></div><?php endif; ?>
<?php if (!empty($warnings)): ?>
    <div class="alert alert-warning">
        <strong>Advertencias:</strong>
        <ul class="mb-0">
            <?php foreach ($warnings as $w): ?>
                <li><?= htmlspecialchars($w) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate id="reporteForm">
    <div class="col-md-6">
        <label for="tipo" class="form-label">Tipo de Reporte <span class="text-danger">*</span></label>
        <select name="tipo" id="tipo" class="form-select" required onchange="cambiarTipo()">
            <option value="acto_inseguro" <?= $tipo_seleccionado == 'acto_inseguro' ? 'selected' : '' ?>>Acto Inseguro</option>
            <option value="accidente" <?= $tipo_seleccionado == 'accidente' ? 'selected' : '' ?>>Accidente</option>
        </select>
    </div>

    <!-- Búsqueda AJAX de empleado -->
    <div class="col-md-6">
        <label for="buscar_empleado" class="form-label">Empleado <span class="text-danger">*</span></label>
        <div class="position-relative">
            <input type="text" class="form-control" id="buscar_empleado" name="buscar_empleado" 
                   placeholder="Escriba número o nombre (mín. 2 caracteres)" autocomplete="off" required
                   value="<?= htmlspecialchars($_POST['buscar_empleado'] ?? '') ?>">
            <div id="empleado-sugerencias" class="list-group position-absolute w-100" style="z-index:1000; max-height:250px; overflow-y:auto; display:none;"></div>
        </div>
        <input type="hidden" name="empleado_id" id="empleado_id" value="<?= htmlspecialchars($_POST['empleado_id'] ?? '') ?>">
        <div class="invalid-feedback">Seleccione un empleado válido de la lista.</div>
        <small class="text-muted">Comience a escribir para buscar.</small>
    </div>

    <div class="col-md-6">
        <label for="departamento_nombre" class="form-label">Departamento <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="departamento_nombre" readonly disabled value="<?= htmlspecialchars($_POST['departamento_nombre'] ?? '') ?>">
        <input type="hidden" name="departamento_id" id="departamento_id" value="<?= htmlspecialchars($_POST['departamento_id'] ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label for="sucursal_nombre" class="form-label">Sucursal <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="sucursal_nombre" readonly disabled value="<?= htmlspecialchars($_POST['sucursal_nombre'] ?? '') ?>">
        <input type="hidden" name="sucursal_id" id="sucursal_id" value="<?= htmlspecialchars($_POST['sucursal_id'] ?? '') ?>">
    </div>

    <div class="col-md-6">
        <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
        <input type="date" name="fecha" id="fecha" class="form-control" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
    </div>
    <div class="col-md-6">
        <label for="hora" class="form-label">Hora <span class="text-danger">*</span></label>
        <input type="time" name="hora" id="hora" class="form-control" value="<?= htmlspecialchars($_POST['hora'] ?? date('H:i')) ?>" required>
    </div>

    <div class="col-md-6" id="contenedor-catalogo">
        <label for="catalogo_id" class="form-label"><?= $labelCatalogo ?> <span class="text-danger">*</span></label>
        <select name="catalogo_id" id="catalogo_id" class="form-select" required>
            <option value="">Seleccione...</option>
            <?php foreach ($catalogo as $item): ?>
                <option value="<?= $item['id'] ?>" <?= (isset($_POST['catalogo_id']) && $_POST['catalogo_id'] == $item['id']) ? 'selected' : '' ?>><?= htmlspecialchars($item['descripcion']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="camposAccidente" style="display: <?= $tipo_seleccionado == 'accidente' ? 'block' : 'none' ?>; width: 100%;">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="gravedad" class="form-label">Gravedad</label>
                <select name="gravedad" id="gravedad" class="form-select">
                    <option value="">Seleccione...</option>
                    <option value="leve" <?= (isset($_POST['gravedad']) && $_POST['gravedad'] == 'leve') ? 'selected' : '' ?>>Leve</option>
                    <option value="moderado" <?= (isset($_POST['gravedad']) && $_POST['gravedad'] == 'moderado') ? 'selected' : '' ?>>Moderado</option>
                    <option value="grave" <?= (isset($_POST['gravedad']) && $_POST['gravedad'] == 'grave') ? 'selected' : '' ?>>Grave</option>
                    <option value="fatal" <?= (isset($_POST['gravedad']) && $_POST['gravedad'] == 'fatal') ? 'selected' : '' ?>>Fatal</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="atencion_medica_id" class="form-label">Atención Médica</label>
                <select name="atencion_medica_id" id="atencion_medica_id" class="form-select" onchange="toggleCamposAdicionales()">
                    <option value="">Ninguna / No aplica</option>
                    <?php foreach ($atenciones as $a): ?>
                        <?php $isIMSS = (stripos($a['descripcion'], 'IMSS') !== false); ?>
                        <option value="<?= $a['id'] ?>" <?= (isset($_POST['atencion_medica_id']) && $_POST['atencion_medica_id'] == $a['id']) ? 'selected' : '' ?> data-es-imss="<?= $isIMSS ? '1' : '0' ?>"><?= htmlspecialchars($a['descripcion']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6" id="campo-dias-perdidos">
                <label for="dias_perdidos" class="form-label">Días de incapacidad</label>
                <input type="number" name="dias_perdidos" id="dias_perdidos" class="form-control" min="0" value="<?= htmlspecialchars($_POST['dias_perdidos'] ?? '0') ?>">
            </div>
            <div class="col-md-6" id="campo-st7" style="display: none;">
                <div class="form-check mt-4">
                    <input type="checkbox" name="st7" id="st7" class="form-check-input" value="1" <?= isset($_POST['st7']) ? 'checked' : '' ?>>
                    <label for="st7" class="form-check-label">¿Riesgo de Trabajo (ST7)?</label>
                </div>
            </div>
            <div class="col-md-6" id="campo-costo" style="display: none;">
                <label for="costo_atencion" class="form-label">Costo de atención ($)</label>
                <input type="number" name="costo_atencion" id="costo_atencion" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($_POST['costo_atencion'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="col-12">
        <label for="observacion" class="form-label">Observación / Comentario</label>
        <textarea name="observacion" id="observacion" class="form-control" rows="3"><?= htmlspecialchars($_POST['observacion'] ?? '') ?></textarea>
    </div>

    <!-- Sección de evidencias múltiples -->
    <div class="col-12">
        <label class="form-label">Evidencias (fotos o documentos)</label>
        <div class="card">
            <div class="card-body">
                <input type="file" name="evidencias[]" id="evidencias" class="form-control" accept="image/*,.pdf,.doc,.docx" multiple style="display: none;">
                <button type="button" class="btn btn-outline-primary w-100" onclick="document.getElementById('evidencias').click();">
                    <i class="fas fa-cloud-upload-alt"></i> Seleccionar archivos (máx. 10 MB c/u)
                </button>
                <small class="text-muted d-block mt-2">Formatos: JPG, PNG, GIF, PDF, DOC, DOCX. Puede seleccionar varios archivos.</small>
                <div id="preview-container" class="row g-2 mt-3"></div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
        <a href="listar.php?tipo=<?= $tipo_seleccionado ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
// Cambiar tipo
function cambiarTipo() {
    const tipo = document.getElementById('tipo').value;
    window.location.href = 'crear.php?tipo=' + tipo;
}
function toggleCamposAccidente() {
    document.getElementById('camposAccidente').style.display = document.getElementById('tipo').value === 'accidente' ? 'block' : 'none';
}
toggleCamposAccidente();

// Mostrar/ocultar campos según atención médica
function toggleCamposAdicionales() {
    const select = document.getElementById('atencion_medica_id');
    const selectedOption = select.options[select.selectedIndex];
    const esIMSS = selectedOption.dataset.esImss === '1';
    document.getElementById('campo-st7').style.display = esIMSS ? 'block' : 'none';
    document.getElementById('campo-costo').style.display = (!esIMSS && select.value !== '') ? 'block' : 'none';
}
document.getElementById('atencion_medica_id').addEventListener('change', toggleCamposAdicionales);
toggleCamposAdicionales();

// ========== BÚSQUEDA AJAX DE EMPLEADO ==========
const inputBuscar = document.getElementById('buscar_empleado');
const sugerenciasDiv = document.getElementById('empleado-sugerencias');
const empleadoIdHidden = document.getElementById('empleado_id');
const deptoNombre = document.getElementById('departamento_nombre');
const deptoIdHidden = document.getElementById('departamento_id');
const sucNombre = document.getElementById('sucursal_nombre');
const sucIdHidden = document.getElementById('sucursal_id');

let timeoutId = null;

inputBuscar.addEventListener('input', function() {
    const termino = this.value.trim();
    clearTimeout(timeoutId);
    if (termino.length < 2) { sugerenciasDiv.style.display = 'none'; return; }
    timeoutId = setTimeout(async () => {
        try {
            const resp = await fetch(`<?= BASE_URL ?>api/buscar_empleados.php?q=${encodeURIComponent(termino)}`);
            const data = await resp.json();
            if (data.length === 0) {
                sugerenciasDiv.innerHTML = '<div class="list-group-item text-muted">No se encontraron empleados</div>';
                sugerenciasDiv.style.display = 'block';
                return;
            }
            sugerenciasDiv.innerHTML = '';
            data.forEach(emp => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = `${emp.numero_empleado} - ${emp.nombre}`;
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    inputBuscar.value = `${emp.numero_empleado} - ${emp.nombre}`;
                    empleadoIdHidden.value = emp.id;
                    sugerenciasDiv.style.display = 'none';
                    inputBuscar.setCustomValidity('');
                    actualizarDatosEmpleado(emp.id);
                });
                sugerenciasDiv.appendChild(item);
            });
            sugerenciasDiv.style.display = 'block';
        } catch (e) { console.error(e); }
    }, 300);
});

document.addEventListener('click', (e) => {
    if (!inputBuscar.contains(e.target) && !sugerenciasDiv.contains(e.target)) sugerenciasDiv.style.display = 'none';
});

async function actualizarDatosEmpleado(empleadoId) {
    try {
        const resp = await fetch(`<?= BASE_URL ?>api/get_departamento.php?empleado_id=${empleadoId}`);
        const data = await resp.json();
        if (data.departamento_id) {
            deptoNombre.value = data.departamento_nombre;
            deptoIdHidden.value = data.departamento_id;
            sucNombre.value = data.sucursal_nombre || '';
            sucIdHidden.value = data.sucursal_id || '';
        }
    } catch (e) { console.error(e); }
}

// Validación de formulario
document.getElementById('reporteForm').addEventListener('submit', function(e) {
    if (!empleadoIdHidden.value) {
        e.preventDefault();
        inputBuscar.classList.add('is-invalid');
        inputBuscar.setCustomValidity('Seleccione un empleado de la lista.');
        inputBuscar.reportValidity();
    } else { inputBuscar.setCustomValidity(''); }
    if (!this.checkValidity()) { e.preventDefault(); this.classList.add('was-validated'); }
});

// ========== MANEJO DE ARCHIVOS CON PREVIEW ==========
const fileInput = document.getElementById('evidencias');
const previewContainer = document.getElementById('preview-container');
let selectedFiles = []; // Array simple con objetos File

fileInput.addEventListener('change', function() {
    for (let file of this.files) {
        // Evitar duplicados exactos
        if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
            selectedFiles.push(file);
        }
    }
    renderPreview();
    // No limpiamos fileInput.value aquí para mantener los archivos en el input real
});

function renderPreview() {
    previewContainer.innerHTML = '';
    selectedFiles.forEach((file, index) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';
        const card = document.createElement('div');
        card.className = 'card h-100';
        let previewHtml = '';
        if (file.type.startsWith('image/')) {
            previewHtml = `<img src="${URL.createObjectURL(file)}" class="card-img-top" style="height: 120px; object-fit: cover;">`;
        } else {
            const icon = file.name.endsWith('.pdf') ? 'fa-file-pdf' : 'fa-file-alt';
            previewHtml = `<div class="card-body text-center py-4"><i class="fas ${icon} fa-3x text-secondary"></i></div>`;
        }
        card.innerHTML = `${previewHtml}
            <div class="card-body p-2"><small class="text-truncate d-block" title="${file.name}">${file.name}</small><small class="text-muted">${(file.size / 1024).toFixed(1)} KB</small></div>
            <div class="card-footer p-1 text-center"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeFile(${index})"><i class="fas fa-trash"></i></button></div>`;
        col.appendChild(card);
        previewContainer.appendChild(col);
    });
    
    // Actualizar input file con los archivos seleccionados (usando DataTransfer como fallback)
    const dt = new DataTransfer();
    selectedFiles.forEach(file => dt.items.add(file));
    fileInput.files = dt.files;
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderPreview();
}

window.removeFile = removeFile;

<?php if (!empty($_POST['empleado_id'])): ?>
    empleadoIdHidden.value = '<?= $_POST['empleado_id'] ?>';
    actualizarDatosEmpleado(<?= $_POST['empleado_id'] ?>);
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>