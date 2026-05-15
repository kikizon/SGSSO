<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) exit;

$empleado_id = $_GET['id'] ?? 0;

$sql = "SELECT a.id, a.nombre, a.descripcion, ea.fecha_registro, ea.observaciones
        FROM empleado_alergia ea 
        JOIN alergias a ON ea.alergia_id = a.id
        WHERE ea.empleado_id = ? 
        ORDER BY a.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute([$empleado_id]);
$alergias = $stmt->fetchAll();
?>

<div class="card mb-3">
    <div class="card-header p-2 d-flex justify-content-between align-items-center">
        <span class="fw-bold">Alergias registradas</span>
        <button class="btn btn-sm btn-outline-success" id="btnAsignarAlergia">
            <i class="fas fa-plus"></i> Asignar Alergia
        </button>
    </div>
</div>

<?php if (empty($alergias)): ?>
    <div class="alert alert-info" id="alergias-empty-msg">No se han registrado alergias.</div>
    <div id="alergias-table-container" style="display:none;"></div>
<?php else: ?>
    <div id="alergias-empty-msg" style="display:none;"></div>
    <div id="alergias-table-container">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead><tr><th>Alergia</th><th>Descripción</th><th>Registrada</th><th>Observaciones</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php foreach ($alergias as $a): ?>
                    <tr id="alergia-<?= $a['id'] ?>">
                        <td><?= htmlspecialchars($a['nombre']) ?></td>
                        <td><?= htmlspecialchars($a['descripcion'] ?? '—') ?></td>
                        <td><?= date('d/m/Y', strtotime($a['fecha_registro'])) ?></td>
                        <td>
                            <span class="editable-observacion" 
                                  data-empleado-id="<?= $empleado_id ?>" 
                                  data-alergia-id="<?= $a['id'] ?>"
                                  title="Haga clic para editar"
                                  style="cursor: pointer; border-bottom: 1px dashed #999;">
                                <?= htmlspecialchars($a['observaciones'] ?? '—') ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger btn-desmarcar-alergia" 
                                    data-empleado-id="<?= $empleado_id ?>" 
                                    data-alergia-id="<?= $a['id'] ?>"
                                    title="Eliminar alergia">
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