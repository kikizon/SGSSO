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
    $whereEstado = "WHERE activo = ?";
    $params[] = $estado;
}

$sql = "SELECT a.*, 
               (SELECT COUNT(DISTINCT ea.empleado_id) 
                FROM empleado_alergia ea 
                JOIN empleados e ON ea.empleado_id = e.id 
                WHERE ea.alergia_id = a.id AND e.activo = 1) as total_empleados
        FROM alergias a
        $whereEstado
        ORDER BY a.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alergias = $stmt->fetchAll();

include '../../includes/header.php';
?>

<h2><i class="fas fa-allergies"></i> Catálogo de Alergias</h2>
<a href="crear.php" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Nueva Alergia</a>
<a href="plantilla.php" class="btn btn-outline-secondary mb-3 no-spinner"><i class="fas fa-download"></i> Plantilla CSV</a>
<a href="importar.php" class="btn btn-success mb-3"><i class="fas fa-upload"></i> Importar CSV</a>

<!-- Filtros compactos alineados a la derecha -->
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
            <tr><th>Nombre</th><th>Descripción</th><th>Empleados</th><th>Estado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php foreach ($alergias as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['nombre']) ?></td>
                <td><?= htmlspecialchars($a['descripcion'] ?? '—') ?></td>
                <td><?= $a['total_empleados'] > 0 ? '<span class="badge bg-info">'.$a['total_empleados'].'</span>' : '<span class="text-muted">—</span>' ?></td>
                <td><?= $a['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-info ver-cobertura" data-id="<?= $a['id'] ?>" data-nombre="<?= htmlspecialchars($a['nombre']) ?>" title="Ver cobertura"><i class="fas fa-chart-pie"></i></button>
                    <a href="editar.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                    <a href="eliminar.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')" title="Eliminar"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal de Cobertura -->
<div class="modal fade" id="coberturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" id="coberturaModalContent">
            <div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>
        </div>
    </div>
</div>

<script>
let filtrosActuales = {};
let pestañaActiva = 'tienen';

function recargarCobertura(alergiaId, filtros = {}) {
    filtrosActuales = Object.assign({}, filtros);
    const modalContent = document.getElementById('coberturaModalContent');
    modalContent.innerHTML = '<div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>';
    const params = new URLSearchParams({id: alergiaId, ...filtros});
    fetch(`info_alergia.php?${params.toString()}`)
        .then(response => response.text())
        .then(html => {
            modalContent.innerHTML = html;
            setTimeout(() => {
                const tabTienen = document.getElementById('tienen-tab');
                const tabNoTienen = document.getElementById('no-tienen-tab');
                const paneTienen = document.getElementById('tienen');
                const paneNoTienen = document.getElementById('noTienen');
                if (pestañaActiva === 'tienen') {
                    tabTienen?.classList.add('active');
                    tabNoTienen?.classList.remove('active');
                    paneTienen?.classList.add('show', 'active');
                    paneNoTienen?.classList.remove('show', 'active');
                } else {
                    tabNoTienen?.classList.add('active');
                    tabTienen?.classList.remove('active');
                    paneNoTienen?.classList.add('show', 'active');
                    paneTienen?.classList.remove('show', 'active');
                }
            }, 50);
        })
        .catch(err => modalContent.innerHTML = '<div class="alert alert-danger m-3">Error al cargar.</div>');
}

function aplicarFiltrosCobertura() {
    const form = document.getElementById('formFiltrosCobertura');
    if (!form) return;
    const alergiaId = form.dataset.alergiaId;
    const formData = new FormData(form);
    const filtros = Object.fromEntries(formData.entries());
    const activeTab = document.querySelector('#coberturaTabs .nav-link.active');
    pestañaActiva = (activeTab && activeTab.id === 'no-tienen-tab') ? 'noTienen' : 'tienen';
    recargarCobertura(alergiaId, filtros);
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

async function toggleAlergia(empleadoId, alergiaId, accion, rowElement) {
    const formData = new FormData();
    formData.append('empleado_id', empleadoId);
    formData.append('alergia_id', alergiaId);
    formData.append('accion', accion);
    try {
        const response = await fetch('marcar_alergia.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            const cells = rowElement.querySelectorAll('td');
            const numero = cells[0].innerText.trim();
            const nombre = cells[1].innerText.trim();
            const depto = cells[2].innerText.trim();
            const suc = cells[3].innerText.trim();
            rowElement.style.transition = 'opacity 0.3s';
            rowElement.style.opacity = '0';
            setTimeout(() => {
                rowElement.remove();
                const nuevaFila = document.createElement('tr');
                nuevaFila.id = `emp-${empleadoId}`;
                nuevaFila.style.opacity = '0';
                nuevaFila.style.transition = 'opacity 0.3s';
                if (accion === 'tiene') {
                    nuevaFila.innerHTML = `<td>${numero}</td><td>${nombre}</td><td>${depto}</td><td>${suc}</td><td>${new Date().toLocaleDateString('es-MX')}</td>
                        <td><button class="btn btn-sm btn-outline-danger btn-desmarcar" data-empleado-id="${empleadoId}" data-alergia-id="${alergiaId}"><i class="fas fa-times"></i> No tiene</button></td>`;
                    document.querySelector('#tienen tbody').appendChild(nuevaFila);
                } else {
                    nuevaFila.innerHTML = `<td>${numero}</td><td>${nombre}</td><td>${depto}</td><td>${suc}</td>
                        <td><button class="btn btn-sm btn-outline-success btn-marcar" data-empleado-id="${empleadoId}" data-alergia-id="${alergiaId}"><i class="fas fa-check"></i> Tiene</button></td>`;
                    document.querySelector('#noTienen tbody').appendChild(nuevaFila);
                }
                setTimeout(() => { nuevaFila.style.opacity = '1'; }, 10);
                actualizarContadores();
            }, 300);
        } else {
            alert('Error: ' + (data.error || 'No se pudo realizar'));
        }
    } catch (e) {
        console.error(e);
        alert('Error de conexión');
    }
}

function actualizarContadores() {
    const totalTienen = document.querySelectorAll('#tienen tbody tr').length;
    const totalNoTienen = document.querySelectorAll('#noTienen tbody tr').length;
    const tabTienen = document.querySelector('#tienen-tab');
    const tabNoTienen = document.querySelector('#no-tienen-tab');
    if (tabTienen) tabTienen.innerHTML = `<i class="fas fa-check-circle text-success"></i> Tienen (${totalTienen})`;
    if (tabNoTienen) tabNoTienen.innerHTML = `<i class="fas fa-times-circle text-danger"></i> No tienen (${totalNoTienen})`;
    const total = totalTienen + totalNoTienen;
    const pct = total > 0 ? Math.round((totalTienen / total) * 1000) / 10 : 0;
    const cardPrimary = document.querySelector('.card.bg-primary .card-body h3');
    const cardSuccess = document.querySelector('.card.bg-success .card-body h3');
    const cardWarning = document.querySelector('.card.bg-warning .card-body h3');
    const cardInfo = document.querySelector('.card.bg-info .card-body h3');
    if (cardPrimary) cardPrimary.innerText = total;
    if (cardSuccess) cardSuccess.innerText = totalTienen;
    if (cardWarning) cardWarning.innerText = totalNoTienen;
    if (cardInfo) cardInfo.innerText = pct + '%';
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('coberturaModal'));
    const modalContent = document.getElementById('coberturaModalContent');
    document.querySelectorAll('.ver-cobertura').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            filtrosActuales = {};
            pestañaActiva = 'tienen';
            modalContent.innerHTML = '<div class="text-center p-3"><div class="spinner-border"></div> Cargando...</div>';
            modal.show();
            recargarCobertura(id);
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.target.matches('#formFiltrosCobertura input[type="text"]') && e.key === 'Enter') {
            e.preventDefault();
            aplicarFiltrosCobertura();
        }
    });

    document.body.addEventListener('click', function(e) {
        if (e.target.closest('#btnFiltrarCobertura')) {
            e.preventDefault();
            aplicarFiltrosCobertura();
        }
        if (e.target.closest('#btnLimpiarCobertura')) {
            e.preventDefault();
            limpiarFiltrosCobertura();
        }
        const btnMarcar = e.target.closest('.btn-marcar');
        if (btnMarcar) {
            e.preventDefault();
            toggleAlergia(btnMarcar.dataset.empleadoId, btnMarcar.dataset.alergiaId, 'tiene', btnMarcar.closest('tr'));
        }
        const btnDesmarcar = e.target.closest('.btn-desmarcar');
        if (btnDesmarcar) {
            e.preventDefault();
            toggleAlergia(btnDesmarcar.dataset.empleadoId, btnDesmarcar.dataset.alergiaId, 'no_tiene', btnDesmarcar.closest('tr'));
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>