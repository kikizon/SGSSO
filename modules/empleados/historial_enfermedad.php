<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) exit;

$empleado_id = $_GET['id'] ?? 0;

$sql = "SELECT e.id, e.nombre, e.descripcion, ee.fecha_registro, ee.observaciones
        FROM empleado_enfermedad ee 
        JOIN enfermedades_cronicas e ON ee.enfermedad_id = e.id
        WHERE ee.empleado_id = ? 
        ORDER BY e.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute([$empleado_id]);
$enfermedades = $stmt->fetchAll();
?>

<div class="card mb-3">
    <div class="card-header p-2 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Enfermedades Crónicas registradas</span>
        <button class="btn btn-sm btn-outline-success" id="btnAsignarEnfermedad">
            <i class="fas fa-plus"></i> Asignar Enfermedad
        </button>
    </div>
</div>

<?php if (empty($enfermedades)): ?>
    <div class="alert alert-info" id="enfermedades-empty-msg">No se han registrado enfermedades.</div>
    <div id="enfermedades-table-container" style="display:none;"></div>
<?php else: ?>
    <div id="enfermedades-empty-msg" style="display:none;"></div>
    <div id="enfermedades-table-container">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead><tr><th>Enfermedad</th><th>Descripción</th><th>Registrada</th><th>Observaciones</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($enfermedades as $e): ?>
                    <tr id="enfermedad-<?= $e['id'] ?>">
                        <td><?= htmlspecialchars($e['nombre']) ?></td>
                        <td><?= htmlspecialchars($e['descripcion'] ?? '—') ?></td>
                        <td><?= date('d/m/Y', strtotime($e['fecha_registro'])) ?></td>
                        <td>
                            <span class="editable-observacion" 
                                  data-tipo="enfermedad"
                                  data-empleado-id="<?= $empleado_id ?>" 
                                  data-enfermedad-id="<?= $e['id'] ?>"
                                  title="Haga clic para editar"
                                  style="cursor: pointer; border-bottom: 1px dashed #999;">
                                <?= htmlspecialchars($e['observaciones'] ?? '—') ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger btn-desmarcar-enfermedad" 
                                    data-empleado-id="<?= $empleado_id ?>" 
                                    data-enfermedad-id="<?= $e['id'] ?>"
                                    title="Eliminar enfermedad">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>