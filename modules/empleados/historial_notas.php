<?php
require_once '../../includes/auth.php';
if (!isset($usuario_id)) { http_response_code(403); exit; }

$empleado_id = (int)($_GET['id'] ?? 0);
if (!$empleado_id) { echo '<div class="alert alert-danger">ID no válido.</div>'; exit; }

$puede = in_array($usuario_rol, ['admin', 'supervisor'], true);

$st = $pdo->prepare("SELECT * FROM empleado_notas WHERE empleado_id = ? ORDER BY creado_en DESC, id DESC");
$st->execute([$empleado_id]);
$notas = $st->fetchAll();
?>

<?php if ($puede): ?>
<div class="card mb-3">
    <div class="card-body">
        <label class="form-label small mb-1">Nueva nota</label>
        <textarea id="notaTexto" class="form-control mb-2" rows="2" maxlength="1000" placeholder="Escribe una nota sobre este empleado..."></textarea>
        <button type="button" class="btn btn-primary btn-sm btn-guardar-nota" data-empleado-id="<?= $empleado_id ?>">
            <i class="fas fa-plus"></i> Agregar nota
        </button>
    </div>
</div>
<?php endif; ?>

<?php if (empty($notas)): ?>
    <div class="alert alert-info mb-0">Este empleado no tiene notas.</div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($notas as $n): ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
                <div class="small text-muted">
                    <i class="fas fa-user-pen"></i> <?= htmlspecialchars($n['autor_nombre'] ?: 'Usuario') ?>
                    · <?= date('d/m/Y H:i', strtotime($n['creado_en'])) ?>
                </div>
                <?php if ($puede): ?>
                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-nota" data-nota-id="<?= (int)$n['id'] ?>" title="Eliminar nota">
                    <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="mt-1" style="white-space:pre-wrap;"><?= htmlspecialchars($n['nota']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
