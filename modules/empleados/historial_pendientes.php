<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) exit;

$empleado_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT numero_empleado, nombre, sucursal_id, departamento_id FROM empleados WHERE id = ?");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch();
if (!$empleado) exit;

$sql = "SELECT c.id, c.nombre, MAX(ca.obligatorio) as obligatorio
        FROM cursos c
        JOIN curso_asignaciones ca ON c.id = ca.curso_id
        WHERE c.activo = 1
          AND (c.sucursal_id IS NULL OR c.sucursal_id = ?)
          AND (
              (ca.tipo_asignacion = 'todos')
              OR (ca.tipo_asignacion = 'sucursal' AND ca.entidad_id = ?)
              OR (ca.tipo_asignacion = 'departamento' AND ca.entidad_id = ?)
              OR (ca.tipo_asignacion = 'empleado' AND ca.entidad_id = ?)
              OR (ca.tipo_asignacion = 'excepto_empleado' AND NOT EXISTS (
                    SELECT 1 FROM curso_asignaciones x
                    WHERE x.curso_id = c.id AND x.tipo_asignacion = 'excepto_empleado' AND x.entidad_id = ?))
          )
          AND c.id NOT IN (SELECT curso_id FROM empleado_curso WHERE empleado_id = ?)
        GROUP BY c.id, c.nombre
        ORDER BY obligatorio DESC, c.nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute([$empleado['sucursal_id'], $empleado['sucursal_id'], $empleado['departamento_id'], $empleado_id, $empleado_id, $empleado_id]);
$pendientes = $stmt->fetchAll();
?>
<div class="card mb-3">
    <div class="card-header p-2">
        <span class="fw-bold">Cursos/Formatos pendientes (obligatorios primero)</span>
    </div>
</div>
<?php if (empty($pendientes)): ?>
    <div class="alert alert-success">¡Este empleado no tiene cursos/formatos pendientes!</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>Curso/Formato</th><th>Obligatorio</th><th>Acción</th></tr></thead>
            <tbody>
                <?php foreach ($pendientes as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= $p['obligatorio'] ? '<span class="badge bg-danger">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-success btn-marcar-pendiente" 
                                data-empleado-id="<?= $empleado_id ?>" 
                                data-curso-id="<?= $p['id'] ?>"
                                title="Marcar como tomado">
                            <i class="fas fa-check"></i> Tomado
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
