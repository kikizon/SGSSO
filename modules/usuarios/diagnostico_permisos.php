<?php
/**
 * Diagnóstico de permisos (solo admin).
 * Compara el MAPA (comportamiento actual) contra lo sembrado en la BASE DE DATOS.
 * Debe usarse ANTES de cambiar PERMISOS_FUENTE a 'bd'.
 */
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
exigir('roles.gestionar');

$roles = ['admin' => 'Administrador', 'supervisor' => 'Supervisor', 'usuario' => 'Usuario'];

$mapa = $bd = [];
foreach ($roles as $r => $n) {
    $mapa[$r] = permisos_de_rol($r);
    $bd[$r]   = permisos_de_rol_bd($pdo, $n);
}

// Diferencias
$dif = [];
foreach (permisos_catalogo() as $grupo => $perms) {
    foreach ($perms as $clave => $def) {
        foreach ($roles as $r => $n) {
            $m = in_array($clave, $mapa[$r], true);
            $b = in_array($clave, $bd[$r], true);
            if ($m !== $b) { $dif[] = ['clave' => $clave, 'rol' => $n, 'mapa' => $m, 'bd' => $b]; }
        }
    }
}
$fuente = PERMISOS_FUENTE;

include '../../includes/header.php';
?>

<h2><i class="fas fa-shield-alt"></i> Diagnóstico de permisos</h2>

<div class="alert <?= $fuente === 'bd' ? 'alert-success' : 'alert-info' ?>">
    Fuente activa: <strong><?= $fuente === 'bd' ? 'Base de datos' : 'Mapa por rol (modo compatibilidad)' ?></strong>
    · Permisos en el catálogo: <strong><?= count(permisos_todos()) ?></strong>
</div>

<?php if (empty($dif)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <strong>El mapa y la base de datos coinciden al 100%.</strong>
        <?php if ($fuente !== 'bd'): ?>
            Es seguro cambiar <code>PERMISOS_FUENTE</code> a <code>'bd'</code>.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <strong>Hay <?= count($dif) ?> diferencia(s) entre el mapa y la base de datos.</strong>
        No cambies la fuente a <code>'bd'</code> hasta resolverlas (vuelve a correr la migración <code>15_permisos.sql</code>).
    </div>
    <div class="card mb-4 border-danger">
        <div class="card-header bg-danger text-white">Diferencias</div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Permiso</th><th>Rol</th><th class="text-center">Mapa</th><th class="text-center">Base de datos</th></tr></thead>
                <tbody>
                    <?php foreach ($dif as $d): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($d['clave']) ?></code></td>
                        <td><?= htmlspecialchars($d['rol']) ?></td>
                        <td class="text-center"><?= $d['mapa'] ? '<span class="text-success">Sí</span>' : '<span class="text-muted">No</span>' ?></td>
                        <td class="text-center"><?= $d['bd'] ? '<span class="text-success">Sí</span>' : '<span class="text-muted">No</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="row g-2 mb-3">
    <?php foreach ($roles as $r => $n): ?>
    <div class="col">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="h4 mb-0"><?= count($mapa[$r]) ?> <small class="text-muted">/ <?= count($bd[$r]) ?></small></div>
                <small><?= htmlspecialchars($n) ?></small>
                <div><small class="text-muted">mapa / BD</small></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php foreach (permisos_catalogo() as $grupo => $perms): ?>
<div class="card mb-3">
    <div class="card-header bg-light fw-bold"><?= htmlspecialchars($grupo) ?></div>
    <div class="card-body table-responsive p-0">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Permiso</th><th>Descripción</th>
                    <?php foreach ($roles as $n): ?><th class="text-center"><?= htmlspecialchars($n) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($perms as $clave => $def): ?>
                <tr>
                    <td><code><?= htmlspecialchars($clave) ?></code></td>
                    <td><?= htmlspecialchars($def['label']) ?></td>
                    <?php foreach ($roles as $r => $n):
                        $m = in_array($clave, $mapa[$r], true);
                        $b = in_array($clave, $bd[$r], true); ?>
                        <td class="text-center <?= $m !== $b ? 'table-danger' : '' ?>">
                            <?php if ($m === $b): ?>
                                <?= $m ? '<span class="text-success fw-bold">&#10003;</span>' : '<span class="text-muted">&mdash;</span>' ?>
                            <?php else: ?>
                                <span class="badge bg-danger" title="mapa / base de datos">
                                    <?= $m ? 'Sí' : 'No' ?> / <?= $b ? 'Sí' : 'No' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php include '../../includes/footer.php'; ?>
