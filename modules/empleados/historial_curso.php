<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) exit;

$empleado_id = $_GET['id'] ?? 0;
$sql = "SELECT c.id, c.nombre, c.descripcion, ec.fecha_realizacion, ec.observaciones
        FROM empleado_curso ec 
        JOIN cursos c ON ec.curso_id = c.id
        WHERE ec.empleado_id = ? 
        ORDER BY ec.fecha_realizacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$empleado_id]);
$cursos = $stmt->fetchAll();
?>

<div class="card mb-3">
    <div class="card-header p-2">
        <span class="fw-bold">Cursos/Formatos realizados</span>
    </div>
</div>

<?php if (empty($cursos)): ?>
    <div class="alert alert-info">No se han registrado cursos/formatos.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>Curso/Formato</th><th>Descripción</th><th>Realizado</th><th>Observaciones</th></tr></thead>
            <tbody>
                <?php foreach ($cursos as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['nombre']) ?></td>
                    <td><?= htmlspecialchars($c['descripcion'] ?? '—') ?></td>
                    <td><?= date('d/m/Y', strtotime($c['fecha_realizacion'])) ?></td>
                    <td>
                        <span class="editable-observacion" 
                              data-tipo="curso"
                              data-empleado-id="<?= $empleado_id ?>" 
                              data-curso-id="<?= $c['id'] ?>"
                              title="Haga clic para editar"
                              style="cursor: pointer; border-bottom: 1px dashed #999;">
                            <?= htmlspecialchars($c['observaciones'] ?? '—') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>