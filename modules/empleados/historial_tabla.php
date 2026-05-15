<?php
// Este archivo espera las variables $reportes, $empleado_id, $tipo_filtro, $fecha_desde, $fecha_hasta
if (empty($reportes)): ?>
    <div class="alert alert-info">No se encontraron reportes con los filtros aplicados.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Gravedad</th>
                    <th>Evidencia</th>
                    <th>Reportado por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportes as $r): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($r['fecha'] . ' ' . $r['hora'])) ?></td>
                    <td>
                        <?php if ($r['tipo'] == 'acto_inseguro'): ?>
                            <span class="badge bg-warning text-dark">Acto Inseguro</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Accidente</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['catalogo_descripcion'] ?? '—') ?></td>
                    <td>
                        <?php if ($r['tipo'] == 'accidente' && $r['gravedad']): ?>
                            <span class="badge <?= badgeGravedad($r['gravedad']) ?>"><?= ucfirst($r['gravedad']) ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($r['evidencia_foto']): ?>
                            <a href="#" class="lightbox-trigger" data-img="<?= UPLOAD_URL . $r['evidencia_foto'] ?>">
                                <i class="fas fa-image"></i>
                            </a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['reportado_por']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>