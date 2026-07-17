<?php
require_once '../../includes/auth.php';
if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

$estado = $_GET['estado'] ?? '1';

$whereEstado = '';
$params = [];
if ($estado !== '') {
    $whereEstado = "WHERE c.activo = ?";
    $params[] = $estado;
}

$sql = "SELECT c.*, s.nombre AS suc_nombre, s.color AS suc_color,
               (SELECT COUNT(DISTINCT ec.empleado_id) 
                FROM empleado_curso ec 
                JOIN empleados e ON ec.empleado_id = e.id 
                WHERE ec.curso_id = c.id AND e.activo = 1) as total_empleados
        FROM cursos c
        LEFT JOIN sucursales s ON s.id = c.sucursal_id
        $whereEstado
        ORDER BY c.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cursos = $stmt->fetchAll();

$sucursales = $pdo->query("SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY nombre")->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-chalkboard-teacher"></i> Catálogo de Cursos/Formatos</h2>
<?php if (($_GET['rep'] ?? '') === 'ok'): ?><div class="alert alert-success">Curso replicado. Revisa la copia en la sucursal destino.</div>
<?php elseif (($_GET['rep'] ?? '') === 'dup'): ?><div class="alert alert-warning">Ya existe un curso con ese nombre en la sucursal destino.</div>
<?php elseif (($_GET['rep'] ?? '') === 'err'): ?><div class="alert alert-danger">No se pudo replicar el curso.</div><?php endif; ?>
<a href="crear.php" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Nuevo Curso/Formato</a>
<!--<a href="plantilla.php" class="btn btn-outline-secondary mb-3 no-spinner"><i class="fas fa-download"></i> Plantilla CSV</a>-->
<a href="importar.php" class="btn btn-success mb-3"><i class="fas fa-upload"></i> Importar CSV</a>

<!-- Filtros compactos -->
<div class="d-flex justify-content-end mb-3">
    <form method="get" class="row g-2 align-items-center">
        <div class="col-auto">
            <label class="col-form-label">Estado:</label>
        </div>
        <div class="col-auto">
            <select name="estado" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Inactivos</option>
                <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <div class="col-auto">
            <a href="listar.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr><th>Nombre</th><th>Sucursal</th><th>Descripción</th><th>Empleados</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php foreach ($cursos as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['nombre']) ?></td>
                <td>
                    <?php if (!empty($c['sucursal_id'])): ?>
                        <span class="badge" style="background-color: <?= htmlspecialchars($c['suc_color'] ?: '#0dcaf0') ?>; color:#fff;"><i class="fas fa-store"></i> <?= htmlspecialchars($c['suc_nombre']) ?></span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Todas</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($c['descripcion'] ?? '—') ?></td>
                <td><?= $c['total_empleados'] > 0 ? '<span class="badge bg-info">'.$c['total_empleados'].'</span>' : '<span class="text-muted">—</span>' ?></td>
                <td><?= $c['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-info ver-cobertura" data-id="<?= $c['id'] ?>" data-nombre="<?= htmlspecialchars($c['nombre']) ?>" title="Ver cobertura"><i class="fas fa-chart-pie"></i></button>
                    <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                    <button class="btn btn-sm btn-outline-primary btn-replicar" data-id="<?= $c['id'] ?>" data-nombre="<?= htmlspecialchars($c['nombre']) ?>" title="Replicar en otra sucursal"><i class="fas fa-copy"></i></button>
                    <a href="eliminar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este curso/formato?')" title="Eliminar"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal para Replicar curso en otra sucursal -->
<div class="modal fade" id="replicarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="replicar.php">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Replicar en otra sucursal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="curso_id" id="rep_curso_id">
                <p class="mb-2">Curso/Formato: <strong id="rep_curso_nombre"></strong></p>
                <label class="form-label">Sucursal destino</label>
                <select name="sucursal_destino" class="form-select" required>
                    <option value="">Seleccione…</option>
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted d-block mt-2">Se crea una copia del curso (nombre, descripción, vigencia) apuntando a esa sucursal. El alcance de <strong>departamento</strong> se conserva; el de <strong>empleados específicos / excepto</strong> no se copia (esos empleados son de la sucursal original), y la copia queda en "Todos" dentro de la sucursal destino.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-copy"></i> Replicar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    const b = e.target.closest('.btn-replicar');
    if (!b) return;
    document.getElementById('rep_curso_id').value = b.dataset.id;
    document.getElementById('rep_curso_nombre').textContent = b.dataset.nombre;
    new bootstrap.Modal(document.getElementById('replicarModal')).show();
});
</script>

<!-- Modal para Cobertura del Curso -->
<div class="modal fade" id="coberturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" id="coberturaModalContent">
            <div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>
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
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM completamente cargado, inicializando modales...');
    
    // Inicializar modales
    const modalCobertura = new bootstrap.Modal(document.getElementById('coberturaModal'));
    const modalFecha = new bootstrap.Modal(document.getElementById('modalFechaCurso'));
    const modalContent = document.getElementById('coberturaModalContent');

    let filtrosActuales = {};
    let pestañaActiva = 'tomaron';
    let cursoIdActual = null;

    function ocultarSpinnerGlobal() {
        const spinner = document.getElementById('loading-spinner');
        if (spinner) spinner.style.display = 'none';
    }

    function limpiarBackdrops() {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
    }

    function recargarCobertura(cursoId, filtros = {}) {
        filtrosActuales = {...filtros};
        cursoIdActual = cursoId;
        modalContent.innerHTML = '<div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>';
        const params = new URLSearchParams({id: cursoId, ...filtros});
        fetch(`info_curso.php?${params.toString()}`)
            .then(response => response.text())
            .then(html => {
                modalContent.innerHTML = html;
                setTimeout(() => {
                    const tabTomaron = document.getElementById('tomaron-tab');
                    const tabNoTomaron = document.getElementById('no-tomaron-tab');
                    const paneTomaron = document.getElementById('tomaron');
                    const paneNoTomaron = document.getElementById('noTomaron');
                    if (pestañaActiva === 'tomaron') {
                        tabTomaron?.classList.add('active');
                        tabNoTomaron?.classList.remove('active');
                        paneTomaron?.classList.add('show', 'active');
                        paneNoTomaron?.classList.remove('show', 'active');
                    } else {
                        tabNoTomaron?.classList.add('active');
                        tabTomaron?.classList.remove('active');
                        paneNoTomaron?.classList.add('show', 'active');
                        paneTomaron?.classList.remove('show', 'active');
                    }
                }, 50);
            })
            .catch(err => modalContent.innerHTML = '<div class="alert alert-danger m-3">Error al cargar.</div>')
            .finally(() => ocultarSpinnerGlobal());
    }

    function aplicarFiltrosCobertura() {
        const form = document.getElementById('formFiltrosCobertura');
        if (!form) return;
        const cursoId = form.dataset.cursoId;
        const formData = new FormData(form);
        const filtros = Object.fromEntries(formData.entries());
        const activeTab = document.querySelector('#coberturaTabs .nav-link.active');
        pestañaActiva = (activeTab && activeTab.id === 'no-tomaron-tab') ? 'noTomaron' : 'tomaron';
        recargarCobertura(cursoId, filtros);
    }

    function limpiarFiltrosCobertura() {
        const form = document.getElementById('formFiltrosCobertura');
        if (!form) return;
        form.sucursal_id.value = '';
        form.departamento_id.value = '';
        form.estado.value = '1';
        form.buscar.value = '';
        aplicarFiltrosCobertura();
    }

    async function toggleCurso(empleadoId, cursoId, accion) {
        const formData = new FormData();
        formData.append('empleado_id', empleadoId);
        formData.append('curso_id', cursoId);
        formData.append('accion', accion);
        
        try {
            const response = await fetch('marcar_curso.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                recargarCobertura(cursoIdActual, filtrosActuales);
            } else {
                alert('Error: ' + (data.error || 'No se pudo realizar la acción'));
            }
        } catch (e) {
            alert('Error de conexión');
        } finally {
            ocultarSpinnerGlobal();
        }
    }

    // Delegación de eventos global
    document.addEventListener('click', (e) => {
        // Abrir modal de cobertura
        const btnCobertura = e.target.closest('.ver-cobertura');
        if (btnCobertura) {
            e.preventDefault();
            console.log('Abriendo cobertura para curso', btnCobertura.dataset.id);
            const id = btnCobertura.dataset.id;
            filtrosActuales = {};
            pestañaActiva = 'tomaron';
            cursoIdActual = id;
            modalContent.innerHTML = '<div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>';
            modalCobertura.show();
            recargarCobertura(id);
        }

        // Botón Filtrar
        if (e.target.closest('#btnFiltrarCobertura')) {
            e.preventDefault();
            aplicarFiltrosCobertura();
        }

        // Botón Limpiar
        if (e.target.closest('#btnLimpiarCobertura')) {
            e.preventDefault();
            limpiarFiltrosCobertura();
        }

        // Botón "Tomado" -> abrir modal de fecha
        const btnMarcar = e.target.closest('.btn-marcar');
        if (btnMarcar) {
            e.preventDefault();
            console.log('Botón Tomado clickeado', btnMarcar.dataset);
            const empleadoId = btnMarcar.dataset.empleadoId;
            const cursoId = btnMarcar.dataset.cursoId || cursoIdActual;
            
            if (!empleadoId || !cursoId) {
                alert('Error: Faltan datos del empleado o curso.');
                return;
            }
            
            document.getElementById('curso_empleado_id').value = empleadoId;
            document.getElementById('curso_curso_id').value = cursoId;
            document.getElementById('curso_fecha_realizacion').value = new Date().toISOString().split('T')[0];
            
            modalFecha.show();
        }

        // Botón "Desmarcar"
        const btnDesmarcar = e.target.closest('.btn-desmarcar');
        if (btnDesmarcar) {
            e.preventDefault();
            console.log('Botón Desmarcar clickeado', btnDesmarcar.dataset);
            const empleadoId = btnDesmarcar.dataset.empleadoId;
            const cursoId = btnDesmarcar.dataset.cursoId || cursoIdActual;
            toggleCurso(empleadoId, cursoId, 'no_tomado');
        }
    });

    // Enviar formulario de fecha
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
            const resp = await fetch('marcar_curso.php', { method: 'POST', body: formData });
            const data = await resp.json();
            
            if (data.success) {
                modalFecha.hide();
                limpiarBackdrops();
                recargarCobertura(cursoIdActual, filtrosActuales);
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

    document.getElementById('modalFechaCurso').addEventListener('hidden.bs.modal', function() {
        limpiarBackdrops();
        ocultarSpinnerGlobal();
    });
    
    console.log('Inicialización completa. Listo para usar.');
});
</script>

<?php include '../../includes/footer.php'; ?>