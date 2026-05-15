<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) {
    http_response_code(403);
    exit;
}

$empleado_id = $_GET['id'] ?? 0;
if (!$empleado_id) {
    echo '<div class="alert alert-danger">ID de empleado no válido.</div>';
    exit;
}

// Datos del empleado
$stmt = $pdo->prepare("SELECT e.*, d.nombre as departamento, s.nombre as sucursal 
                       FROM empleados e
                       JOIN departamentos d ON e.departamento_id = d.id
                       JOIN sucursales s ON e.sucursal_id = s.id
                       WHERE e.id = ?");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch();

if (!$empleado) {
    echo '<div class="alert alert-warning">Empleado no encontrado.</div>';
    exit;
}

// Conteos iniciales para las pestañas
$countReportes = $pdo->prepare("SELECT COUNT(*) FROM reportes WHERE empleado_id = ?");
$countReportes->execute([$empleado_id]);
$totalReportes = $countReportes->fetchColumn();

$countEnfermedades = $pdo->prepare("SELECT COUNT(*) FROM empleado_enfermedad WHERE empleado_id = ?");
$countEnfermedades->execute([$empleado_id]);
$totalEnfermedades = $countEnfermedades->fetchColumn();

$countAlergias = $pdo->prepare("SELECT COUNT(*) FROM empleado_alergia WHERE empleado_id = ?");
$countAlergias->execute([$empleado_id]);
$totalAlergias = $countAlergias->fetchColumn();

$countCursos = $pdo->prepare("SELECT COUNT(*) FROM empleado_curso WHERE empleado_id = ?");
$countCursos->execute([$empleado_id]);
$totalCursos = $countCursos->fetchColumn();

// Cursos pendientes
$sqlPendientes = "SELECT COUNT(DISTINCT c.id)
                  FROM cursos c
                  JOIN curso_asignaciones ca ON c.id = ca.curso_id
                  WHERE c.activo = 1
                    AND (
                        (ca.tipo_asignacion = 'todos')
                        OR (ca.tipo_asignacion = 'sucursal' AND ca.entidad_id = ?)
                        OR (ca.tipo_asignacion = 'departamento' AND ca.entidad_id = ?)
                        OR (ca.tipo_asignacion = 'empleado' AND ca.entidad_id = ?)
                    )
                    AND c.id NOT IN (SELECT curso_id FROM empleado_curso WHERE empleado_id = ?)";
$stmtPendientes = $pdo->prepare($sqlPendientes);
$stmtPendientes->execute([$empleado['sucursal_id'], $empleado['departamento_id'], $empleado_id, $empleado_id]);
$totalPendientes = $stmtPendientes->fetchColumn();
?>

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="fas fa-history"></i> Historial de <?= htmlspecialchars($empleado['nombre']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="row mb-3">
        <div class="col-md-4"><strong>Número:</strong> <?= htmlspecialchars($empleado['numero_empleado']) ?></div>
        <div class="col-md-4"><strong>Departamento:</strong> <?= htmlspecialchars($empleado['departamento']) ?></div>
        <div class="col-md-4"><strong>Sucursal:</strong> <?= htmlspecialchars($empleado['sucursal']) ?></div>
    </div>

    <!-- Pestañas -->
    <ul class="nav nav-tabs" id="historialTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="reportes-tab" data-bs-toggle="tab" data-bs-target="#reportes" type="button" role="tab" data-tipo="reportes">
                <i class="fas fa-exclamation-triangle"></i> Reportes (<?= $totalReportes ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="enfermedades-tab" data-bs-toggle="tab" data-bs-target="#enfermedades" type="button" role="tab" data-tipo="enfermedades">
                <i class="fas fa-heartbeat"></i> Enf. Crónicas (<?= $totalEnfermedades ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="alergias-tab" data-bs-toggle="tab" data-bs-target="#alergias" type="button" role="tab" data-tipo="alergias">
                <i class="fas fa-allergies"></i> Alergias (<?= $totalAlergias ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cursos-tab" data-bs-toggle="tab" data-bs-target="#cursos" type="button" role="tab" data-tipo="cursos">
                <i class="fas fa-chalkboard-teacher"></i> Cursos/Formatos (<?= $totalCursos ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pendientes-tab" data-bs-toggle="tab" data-bs-target="#pendientes" type="button" role="tab" data-tipo="pendientes">
                <i class="fas fa-exclamation-circle text-warning"></i> Pendientes (<?= $totalPendientes ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content mt-3" id="historialTabContent">
        <div class="tab-pane fade show active" id="reportes" role="tabpanel">
            <div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>
        </div>
        <div class="tab-pane fade" id="enfermedades" role="tabpanel">
            <div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>
        </div>
        <div class="tab-pane fade" id="alergias" role="tabpanel">
            <div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>
        </div>
        <div class="tab-pane fade" id="cursos" role="tabpanel">
            <div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>
        </div>
        <div class="tab-pane fade" id="pendientes" role="tabpanel">
            <div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
</div>

<!-- Modal para Asignar Alergia -->
<div class="modal fade" id="modalAsignarAlergia" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-allergies"></i> Asignar Alergia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAsignarAlergiaGlobal">
                    <input type="hidden" name="empleado_id" id="asignar_empleado_id_alergia" value="<?= $empleado_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Seleccione Alergia</label>
                        <select name="alergia_id" id="selectAlergiaGlobal" class="form-select" required>
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Asignar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Asignar Enfermedad -->
<div class="modal fade" id="modalAsignarEnfermedad" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-heartbeat"></i> Asignar Enfermedad Crónica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAsignarEnfermedadGlobal">
                    <input type="hidden" name="empleado_id" id="asignar_empleado_id_enfermedad" value="<?= $empleado_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Seleccione Enfermedad</label>
                        <select name="enfermedad_id" id="selectEnfermedadGlobal" class="form-select" required>
                            <option value="">Cargando...</option>
                        </select>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Asignar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Fecha de Curso -->
<div class="modal fade" id="modalFechaCurso" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Fecha de realización</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formFechaCurso">
                    <input type="hidden" id="curso_empleado_id">
                    <input type="hidden" id="curso_curso_id">
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" id="curso_fecha_realizacion" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
var loadedTabs = {
    reportes: false,
    enfermedades: false,
    alergias: false,
    cursos: false,
    pendientes: false
};

const empleadoIdActual = <?= $empleado_id ?>;
const modalAsignarAlergia = new bootstrap.Modal(document.getElementById('modalAsignarAlergia'));
const modalAsignarEnfermedad = new bootstrap.Modal(document.getElementById('modalAsignarEnfermedad'));
const modalFechaCurso = new bootstrap.Modal(document.getElementById('modalFechaCurso'));

// Inline edit for observations (Allergies, Diseases, Courses)
document.addEventListener('click', (e) => {
    const span = e.target.closest('.editable-observacion');
    if (!span) return;
    e.preventDefault();

    if (span.querySelector('input')) return;

    const tipo = span.dataset.tipo;
    const empleadoId = span.dataset.empleadoId;
    const id = span.dataset.alergiaId || span.dataset.enfermedadId || span.dataset.cursoId;
    const currentText = span.innerText === '—' ? '' : span.innerText;

    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentText;
    input.className = 'form-control form-control-sm';
    input.style.width = '150px';
    input.style.display = 'inline-block';

    span.innerHTML = '';
    span.appendChild(input);
    input.focus();

    const guardar = async () => {
        const newText = input.value.trim();
        span.innerHTML = newText === '' ? '—' : newText;

        let url = '';
        const body = new URLSearchParams();
        body.append('empleado_id', empleadoId);

        if (tipo === 'alergia') {
            url = '<?= BASE_URL ?>modules/empleados/actualizar_observacion_alergia.php';
            body.append('alergia_id', id);
        } else if (tipo === 'enfermedad') {
            url = '<?= BASE_URL ?>modules/empleados/actualizar_observacion_enfermedad.php';
            body.append('enfermedad_id', id);
        } else if (tipo === 'curso') {
            url = '<?= BASE_URL ?>modules/empleados/actualizar_observacion_curso.php';
            body.append('curso_id', id);
        }
        body.append('observaciones', newText);

        if (url) {
            try {
                await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body });
            } catch (err) { console.error(err); }
        }
    };

    input.addEventListener('blur', guardar);
    input.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); input.blur(); } });
});

// Función para ocultar spinner global
function ocultarSpinnerGlobal() {
    const spinner = document.getElementById('loading-spinner');
    if (spinner) spinner.style.display = 'none';
}

// Función para limpiar backdrops
function limpiarBackdrops() {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
}

// Cargar contenido de pestaña
function cargarContenidoPestana(tipo) {
    if (loadedTabs[tipo]) return;
    
    const container = document.getElementById(tipo);
    let url = '';
    
    switch(tipo) {
        case 'reportes': url = `historial_reporte.php?id=${empleadoIdActual}`; break;
        case 'enfermedades': url = `historial_enfermedad.php?id=${empleadoIdActual}`; break;
        case 'alergias': url = `historial_alergia.php?id=${empleadoIdActual}`; break;
        case 'cursos': url = `historial_curso.php?id=${empleadoIdActual}`; break;
        case 'pendientes': url = `historial_pendientes.php?id=${empleadoIdActual}`; break;
    }
    
    if (url) {
        fetch(url)
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
                loadedTabs[tipo] = true;
                if (tipo === 'reportes') inicializarLightbox();
            })
            .catch(err => { container.innerHTML = '<div class="alert alert-danger">Error al cargar</div>'; })
            .finally(() => ocultarSpinnerGlobal());
    }
}

// Cargar reportes automáticamente
cargarContenidoPestana('reportes');

// Eventos de pestañas
document.querySelectorAll('#historialTabs button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function(e) {
        cargarContenidoPestana(this.dataset.tipo);
    });
});

function inicializarLightbox() {
    document.querySelectorAll('#reportes .lightbox-trigger').forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const imgSrc = this.dataset.img || this.href;
            if (imgSrc) {
                const lightboxImg = document.getElementById('lightboxImage');
                if (lightboxImg) {
                    lightboxImg.src = imgSrc;
                    new bootstrap.Modal(document.getElementById('lightboxModal')).show();
                }
            }
        });
    });
}

// ========== DELEGACIÓN DE EVENTOS GLOBAL ==========
document.addEventListener('click', async (e) => {
    // Botón "Asignar Alergia"
    if (e.target.closest('#btnAsignarAlergia')) {
        e.preventDefault();
        await cargarOpcionesAlergias();
        modalAsignarAlergia.show();
    }
    
    // Botón "Asignar Enfermedad"
    if (e.target.closest('#btnAsignarEnfermedad')) {
        e.preventDefault();
        await cargarOpcionesEnfermedades();
        modalAsignarEnfermedad.show();
    }

    // Botón "Tomado" en pendientes
    const btnPendiente = e.target.closest('.btn-marcar-pendiente');
    if (btnPendiente) {
        e.preventDefault();
        const empleadoId = btnPendiente.dataset.empleadoId;
        const cursoId = btnPendiente.dataset.cursoId;
        
        document.getElementById('curso_empleado_id').value = empleadoId;
        document.getElementById('curso_curso_id').value = cursoId;
        document.getElementById('curso_fecha_realizacion').value = new Date().toISOString().split('T')[0];
        
        modalFechaCurso.show();
    }

    // Botón "Eliminar alergia"
    const btnEliminarAlergia = e.target.closest('.btn-desmarcar-alergia');
    if (btnEliminarAlergia) {
        e.preventDefault();
        if (!confirm('¿Eliminar esta alergia?')) return;
        
        const empleadoId = btnEliminarAlergia.dataset.empleadoId;
        const alergiaId = btnEliminarAlergia.dataset.alergiaId;
        const row = btnEliminarAlergia.closest('tr');
        
        try {
            const resp = await fetch('<?= BASE_URL ?>modules/empleados/marcar_alergia.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({empleado_id: empleadoId, alergia_id: alergiaId, accion: 'no_tiene'})
            });
            const data = await resp.json();
            if (data.success) {
                row.remove();
                actualizarContadorAlergias();
                const tableContainer = document.getElementById('alergias-table-container');
                if (tableContainer && tableContainer.querySelectorAll('tbody tr').length === 0) {
                    tableContainer.style.display = 'none';
                    document.getElementById('alergias-empty-msg').style.display = 'block';
                }
            } else {
                alert('Error al eliminar');
            }
        } catch (e) {
            alert('Error de conexión');
        } finally {
            ocultarSpinnerGlobal();
        }
    }

    // Botón "Eliminar enfermedad"
    const btnEliminarEnfermedad = e.target.closest('.btn-desmarcar-enfermedad');
    if (btnEliminarEnfermedad) {
        e.preventDefault();
        if (!confirm('¿Eliminar esta enfermedad?')) return;
        
        const empleadoId = btnEliminarEnfermedad.dataset.empleadoId;
        const enfermedadId = btnEliminarEnfermedad.dataset.enfermedadId;
        const row = btnEliminarEnfermedad.closest('tr');
        
        try {
            const resp = await fetch('<?= BASE_URL ?>modules/empleados/marcar_enfermedad.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({empleado_id: empleadoId, enfermedad_id: enfermedadId, accion: 'no_tiene'})
            });
            const data = await resp.json();
            if (data.success) {
                row.remove();
                actualizarContadorEnfermedades();
                const tableContainer = document.getElementById('enfermedades-table-container');
                if (tableContainer && tableContainer.querySelectorAll('tbody tr').length === 0) {
                    tableContainer.style.display = 'none';
                    document.getElementById('enfermedades-empty-msg').style.display = 'block';
                }
            } else {
                alert('Error al eliminar');
            }
        } catch (e) {
            alert('Error de conexión');
        } finally {
            ocultarSpinnerGlobal();
        }
    }
});

// Cargar opciones de alergias disponibles
async function cargarOpcionesAlergias() {
    const select = document.getElementById('selectAlergiaGlobal');
    select.innerHTML = '<option value="">Cargando...</option>';
    try {
        const resp = await fetch(`<?= BASE_URL ?>modules/empleados/get_alergias_disponibles.php?empleado_id=${empleadoIdActual}`);
        const data = await resp.json();
        select.innerHTML = '<option value="">-- Seleccione --</option>';
        if (data.length === 0) {
            select.disabled = true;
            select.innerHTML = '<option value="">No hay alergias disponibles</option>';
        } else {
            select.disabled = false;
            data.forEach(a => {
                const option = document.createElement('option');
                option.value = a.id;
                option.textContent = a.nombre;
                select.appendChild(option);
            });
        }
    } catch (e) {
        select.innerHTML = '<option value="">Error al cargar</option>';
    }
}

// Cargar opciones de enfermedades disponibles
async function cargarOpcionesEnfermedades() {
    const select = document.getElementById('selectEnfermedadGlobal');
    select.innerHTML = '<option value="">Cargando...</option>';
    try {
        const resp = await fetch(`<?= BASE_URL ?>modules/empleados/get_enfermedades_disponibles.php?empleado_id=${empleadoIdActual}`);
        const data = await resp.json();
        select.innerHTML = '<option value="">-- Seleccione --</option>';
        if (data.length === 0) {
            select.disabled = true;
            select.innerHTML = '<option value="">No hay enfermedades disponibles</option>';
        } else {
            select.disabled = false;
            data.forEach(e => {
                const option = document.createElement('option');
                option.value = e.id;
                option.textContent = e.nombre;
                select.appendChild(option);
            });
        }
    } catch (e) {
        select.innerHTML = '<option value="">Error al cargar</option>';
    }
}

// Enviar formulario de asignación de alergia
document.getElementById('formAsignarAlergiaGlobal').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Asignando...';
    
    const formData = new FormData(this);
    try {
        const resp = await fetch('<?= BASE_URL ?>modules/empleados/asignar_alergia.php', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        
        if (data.success) {
            modalAsignarAlergia.hide();
            limpiarBackdrops();
            loadedTabs.alergias = false;
            cargarContenidoPestana('alergias');
        } else {
            alert('Error: ' + (data.error || 'No se pudo asignar'));
        }
    } catch (e) {
        alert('Error de conexión');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Asignar';
        ocultarSpinnerGlobal();
    }
});

// Enviar formulario de asignación de enfermedad
document.getElementById('formAsignarEnfermedadGlobal').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Asignando...';
    
    const formData = new FormData(this);
    try {
        const resp = await fetch('<?= BASE_URL ?>modules/empleados/asignar_enfermedad.php', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        
        if (data.success) {
            modalAsignarEnfermedad.hide();
            limpiarBackdrops();
            loadedTabs.enfermedades = false;
            cargarContenidoPestana('enfermedades');
        } else {
            alert('Error: ' + (data.error || 'No se pudo asignar'));
        }
    } catch (e) {
        alert('Error de conexión');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Asignar';
        ocultarSpinnerGlobal();
    }
});

// Enviar formulario de fecha de curso
document.getElementById('formFechaCurso').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const empleadoId = document.getElementById('curso_empleado_id').value;
    const cursoId = document.getElementById('curso_curso_id').value;
    const fecha = document.getElementById('curso_fecha_realizacion').value;
    
    if (!fecha) {
        alert('Debe seleccionar una fecha');
        return;
    }
    
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
    
    const formData = new FormData();
    formData.append('empleado_id', empleadoId);
    formData.append('curso_id', cursoId);
    formData.append('accion', 'tomado');
    formData.append('fecha', fecha);
    
    try {
        const resp = await fetch('<?= BASE_URL ?>modules/cursos/marcar_curso.php', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        
        if (data.success) {
            modalFechaCurso.hide();
            limpiarBackdrops();
            loadedTabs.pendientes = false;
            loadedTabs.cursos = false;
            
            const activeTab = document.querySelector('#historialTabs .nav-link.active');
            if (activeTab) {
                const tipo = activeTab.dataset.tipo;
                if (tipo === 'pendientes' || tipo === 'cursos') {
                    cargarContenidoPestana(tipo);
                }
            }
            actualizarContadoresCursos(empleadoId);
        } else {
            alert('Error: ' + (data.error || 'No se pudo marcar'));
        }
    } catch (e) {
        alert('Error de conexión');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Confirmar';
        ocultarSpinnerGlobal();
    }
});

function actualizarContadorAlergias() {
    const rows = document.querySelectorAll('#alergias tbody tr');
    const count = rows.length;
    const tab = document.getElementById('alergias-tab');
    if (tab) tab.innerHTML = `<i class="fas fa-allergies"></i> Alergias (${count})`;
}

function actualizarContadorEnfermedades() {
    const rows = document.querySelectorAll('#enfermedades tbody tr');
    const count = rows.length;
    const tab = document.getElementById('enfermedades-tab');
    if (tab) tab.innerHTML = `<i class="fas fa-heartbeat"></i> Enf. Crónicas (${count})`;
}

async function actualizarContadoresCursos(empleadoId) {
    try {
        const resp = await fetch(`historial_contadores.php?id=${empleadoId}`);
        const data = await resp.json();
        document.getElementById('cursos-tab').innerHTML = `<i class="fas fa-chalkboard-teacher"></i> Cursos (${data.cursos})`;
        document.getElementById('pendientes-tab').innerHTML = `<i class="fas fa-exclamation-circle text-warning"></i> Pendientes (${data.pendientes})`;
    } catch (e) {
        console.error('Error al actualizar contadores', e);
    }
}

// Limpiar backdrops al cerrar modales
['modalAsignarAlergia', 'modalAsignarEnfermedad', 'modalFechaCurso'].forEach(id => {
    document.getElementById(id).addEventListener('hidden.bs.modal', function() {
        limpiarBackdrops();
        ocultarSpinnerGlobal();
    });
});
</script>