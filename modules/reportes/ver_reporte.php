<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo '<div class="alert alert-danger">ID de reporte no válido.</div>';
    exit;
}

$sql = "SELECT r.*, 
               e.numero_empleado, e.nombre as empleado_nombre,
               d.nombre as departamento_nombre,
               s.nombre as sucursal_nombre,
               CASE WHEN r.tipo = 'acto_inseguro' THEN a.descripcion ELSE ta.descripcion END as catalogo_descripcion,
               am.descripcion as atencion_medica,
               u.nombre_completo as reportado_por
        FROM reportes r
        JOIN empleados e ON r.empleado_id = e.id
        JOIN departamentos d ON r.departamento_id = d.id
        JOIN sucursales s ON r.sucursal_id = s.id
        LEFT JOIN actos_inseguros a ON r.acto_inseguro_id = a.id AND r.tipo = 'acto_inseguro'
        LEFT JOIN tipos_accidente ta ON r.accidente_id = ta.id AND r.tipo = 'accidente'
        LEFT JOIN atenciones_medicas am ON r.atencion_medica_id = am.id
        JOIN usuarios u ON r.reportado_por = u.id
        WHERE r.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$reporte = $stmt->fetch();

if (!$reporte) {
    echo '<div class="alert alert-warning">Reporte no encontrado.</div>';
    exit;
}

// Obtener evidencias
$evidencias = $pdo->prepare("SELECT * FROM reportes_evidencias WHERE reporte_id = ?");
$evidencias->execute([$id]);
$evidencias = $evidencias->fetchAll();

$firmados = $pdo->prepare("SELECT * FROM reportes_firmados WHERE reporte_id = ? ORDER BY creado_en DESC");
$firmados->execute([$id]);
$firmados = $firmados->fetchAll();
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

function badgeGravedad($gravedad) {
    return match($gravedad) {
        'leve' => 'bg-success',
        'moderado' => 'bg-warning text-dark',
        'grave' => 'bg-danger',
        'fatal' => 'bg-dark',
        default => 'bg-secondary'
    };
}
?>

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title">
        <i class="fas fa-<?= $reporte['tipo'] == 'acto_inseguro' ? 'skull-crossbones' : 'car-crash' ?>"></i>
        Detalles del Reporte
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="row mb-3">
        <div class="col-md-6">
            <strong>Tipo:</strong> 
            <?php if ($reporte['tipo'] == 'acto_inseguro'): ?>
                <span class="badge bg-warning text-dark">Acto Inseguro</span>
            <?php else: ?>
                <span class="badge bg-danger">Accidente</span>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <strong>Fecha y hora:</strong> 
            <?= date('d/m/Y', strtotime($reporte['fecha'])) ?> a las <?= date('H:i', strtotime($reporte['hora'])) ?>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <strong>Empleado:</strong> <?= htmlspecialchars($reporte['numero_empleado'] . ' - ' . $reporte['empleado_nombre']) ?>
        </div>
        <div class="col-md-4">
            <strong>Departamento:</strong> <?= htmlspecialchars($reporte['departamento_nombre']) ?>
        </div>
        <div class="col-md-4">
            <strong>Sucursal:</strong> <?= htmlspecialchars($reporte['sucursal_nombre']) ?>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <strong><?= $reporte['tipo'] == 'acto_inseguro' ? 'Acto Inseguro' : 'Tipo de Accidente' ?>:</strong>
            <?= htmlspecialchars($reporte['catalogo_descripcion'] ?? '—') ?>
        </div>
        <?php if ($reporte['tipo'] == 'accidente'): ?>
        <div class="col-md-3">
            <strong>Gravedad:</strong>
            <?php if ($reporte['gravedad']): ?>
                <span class="badge <?= badgeGravedad($reporte['gravedad']) ?>"><?= ucfirst($reporte['gravedad']) ?></span>
            <?php else: ?>
                —
            <?php endif; ?>
        </div>
        <div class="col-md-3">
            <strong>Atención Médica:</strong> <?= htmlspecialchars($reporte['atencion_medica'] ?? '—') ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($reporte['tipo'] == 'accidente'): ?>
    <div class="row mb-3">
        <div class="col-md-4">
            <strong>Días de incapacidad:</strong> <?= $reporte['dias_perdidos'] ?: '0' ?>
        </div>
        <?php if (stripos($reporte['atencion_medica'] ?? '', 'IMSS') !== false): ?>
        <div class="col-md-4">
            <strong>Riesgo de Trabajo (ST7):</strong> <?= $reporte['st7'] ? 'Sí' : 'No' ?>
        </div>
        <?php elseif (!empty($reporte['atencion_medica'])): ?>
        <div class="col-md-4">
            <strong>Costo de atención:</strong> $<?= number_format($reporte['costo_atencion'], 2) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="mb-3">
        <strong>Observaciones:</strong><br>
        <?= nl2br(htmlspecialchars($reporte['observacion'] ?? 'Sin observaciones')) ?>
    </div>

    <div class="mb-3">
        <strong>Reportado por:</strong> <?= htmlspecialchars($reporte['reportado_por']) ?> 
        (<?= date('d/m/Y H:i', strtotime($reporte['creado_en'])) ?>)
    </div>

    <!-- Evidencias -->
    <div class="mt-4">
        <h6><i class="fas fa-paperclip"></i> Evidencias (<?= count($evidencias) ?>)</h6>
        <?php if (empty($evidencias)): ?>
            <p class="text-muted">No hay evidencias adjuntas.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($evidencias as $ev): ?>
                <div class="col-6 col-md-4">
                    <div class="card h-100">
                        <?php if ($ev['tipo'] == 'imagen'): ?>
                            <a href="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>" class="lightbox-trigger" data-img="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>">
                                <img src="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>" class="card-img-top" style="height: 120px; object-fit: cover;">
                            </a>
                        <?php else: ?>
                            <div class="card-body text-center py-3">
                                <a href="<?= UPLOAD_URL . $ev['nombre_archivo'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-file fa-2x"></i><br>
                                    Abrir documento
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="card-body p-2">
                            <small class="text-truncate d-block" title="<?= htmlspecialchars($ev['nombre_archivo']) ?>">
                                <?= htmlspecialchars($ev['nombre_archivo']) ?>
                            </small>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($ev['fecha_subida'])) ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?><div class="alert alert-success mt-3"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-warning mt-3"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="mt-4">
    <h6><i class="fas fa-file-signature"></i> Documentos firmados (<?= count($firmados) ?>)</h6>
    <p class="text-muted small mb-2">Descarga el PDF, recábalo firmado (empleado, SYSO y gerencia), escanéalo y súbelo aquí. Puedes subir varios.</p>

    <form action="subir_firmado.php" method="post" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="reporte_id" value="<?= $id ?>">
        <div class="col-auto">
            <input type="file" name="firmados[]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" multiple required>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-upload"></i> Subir firmado(s)</button>
        </div>
    </form>

    <?php if (empty($firmados)): ?>
        <p class="text-muted">Aún no hay documentos firmados.</p>
    <?php else: ?>
        <ul class="list-group">
            <?php foreach ($firmados as $f): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    <i class="fas <?= $f['tipo'] === 'imagen' ? 'fa-image' : 'fa-file-pdf' ?>"></i>
                    <a href="<?= UPLOAD_URL . htmlspecialchars($f['nombre_archivo']) ?>" target="_blank"><?= htmlspecialchars($f['nombre_original'] ?: $f['nombre_archivo']) ?></a>
                    <small class="text-muted">· <?= date('d/m/Y H:i', strtotime($f['creado_en'])) ?></small>
                </span>
                <form action="eliminar_firmado.php" method="post" onsubmit="return confirm('¿Eliminar este documento firmado?');" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="firmado_id" value="<?= $f['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <a href="editar.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Editar</a>
    <a href="generar_pdf_reporte.php?id=<?= $id ?>" class="btn btn-danger no-spinner"><i class="fas fa-file-pdf"></i> Descargar PDF</a>
</div>