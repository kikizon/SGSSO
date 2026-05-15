<?php
require_once '../../includes/auth.php';
$reporte_id = $_GET['id'] ?? 0;
$evidencias = $pdo->prepare("SELECT * FROM reportes_evidencias WHERE reporte_id = ?");
$evidencias->execute([$reporte_id]);
$evidencias = $evidencias->fetchAll();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class="fas fa-images"></i> Evidencias del Reporte</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="row g-3">
        <?php foreach ($evidencias as $ev): ?>
        <div class="col-6 col-md-4">
            <div class="card h-100">
                <?php if ($ev['tipo'] == 'imagen'): ?>
                    <a href="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>" class="lightbox-trigger" data-img="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>">
                        <img src="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>" class="card-img-top" style="height: 150px; object-fit: cover;">
                    </a>
                <?php else: ?>
                    <div class="card-body text-center py-4">
                        <a href="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-file fa-3x"></i><br>
                            Abrir documento
                        </a>
                    </div>
                <?php endif; ?>
                <div class="card-body p-2">
                    <small class="text-truncate"><?= htmlspecialchars($ev['nombre_archivo']) ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
</div>