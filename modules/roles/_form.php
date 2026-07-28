<?php
/**
 * Formulario de rol (compartido por crear.php y editar.php).
 * Espera: $rol (array con nombre/descripcion/activo), $seleccionados (array de claves),
 *         $titulo, $accion (texto del botón).
 */
?>
<h2><i class="fas fa-user-shield"></i> <?= htmlspecialchars($titulo) ?></h2>

<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <?php if (!empty($rol['id'])): ?><input type="hidden" name="id" value="<?= (int)$rol['id'] ?>"><?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">Nombre del rol <span class="text-danger">*</span></label>
            <input type="text" name="nombre" class="form-control" required maxlength="64"
                   value="<?= htmlspecialchars($rol['nombre'] ?? '') ?>" placeholder="Ej. Supervisor de cursos">
        </div>
        <div class="col-md-6">
            <label class="form-label">Descripción</label>
            <input type="text" name="descripcion" class="form-control" maxlength="160"
                   value="<?= htmlspecialchars($rol['descripcion'] ?? '') ?>" placeholder="Para qué sirve este rol">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox" name="activo" id="activo" class="form-check-input" value="1"
                    <?= !isset($rol['activo']) || $rol['activo'] ? 'checked' : '' ?>>
                <label for="activo" class="form-check-label">Activo</label>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-body py-2">
                    <div class="form-check mb-0">
                        <input type="checkbox" name="ve_todas_sucursales" id="ve_todas" class="form-check-input" value="1"
                            <?= !empty($rol['ve_todas_sucursales']) ? 'checked' : '' ?>>
                        <label for="ve_todas" class="form-check-label">
                            <strong>Ver todas las sucursales</strong>
                            <small class="text-muted d-block">
                                Alcance, no permiso: con esto marcado el rol ve los datos de todas las sucursales.
                                Sin marcar, cada usuario queda limitado a las sucursales que tenga asignadas.
                            </small>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Permisos <span class="badge bg-info" id="contadorPerms">0</span></h5>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" id="btnTodos">Marcar todos</button>
            <button type="button" class="btn btn-outline-secondary" id="btnNinguno">Desmarcar todos</button>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach (permisos_catalogo() as $grupo => $perms): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold small"><?= htmlspecialchars($grupo) ?></span>
                    <button type="button" class="btn btn-sm btn-link p-0 grupo-toggle" data-grupo="<?= htmlspecialchars($grupo) ?>">alternar</button>
                </div>
                <div class="card-body py-2">
                    <?php foreach ($perms as $clave => $def): ?>
                    <div class="form-check">
                        <input class="form-check-input perm" type="checkbox" name="permisos[]"
                               value="<?= htmlspecialchars($clave) ?>"
                               id="p_<?= md5($clave) ?>"
                               data-grupo="<?= htmlspecialchars($grupo) ?>"
                               <?= in_array($clave, $seleccionados, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="p_<?= md5($clave) ?>">
                            <?= htmlspecialchars($def['label']) ?>
                            <code class="text-muted" style="font-size:.75em"><?= htmlspecialchars($clave) ?></code>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3 mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= htmlspecialchars($accion) ?></button>
        <a href="listar.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<script>
(function () {
    const perms = () => Array.from(document.querySelectorAll('.perm'));
    const contador = document.getElementById('contadorPerms');
    function actualizar() { contador.textContent = perms().filter(p => p.checked).length; }
    document.getElementById('btnTodos').addEventListener('click', () => { perms().forEach(p => p.checked = true); actualizar(); });
    document.getElementById('btnNinguno').addEventListener('click', () => { perms().forEach(p => p.checked = false); actualizar(); });
    document.querySelectorAll('.grupo-toggle').forEach(b => b.addEventListener('click', () => {
        const g = b.dataset.grupo;
        const items = perms().filter(p => p.dataset.grupo === g);
        const encender = items.some(p => !p.checked);
        items.forEach(p => p.checked = encender);
        actualizar();
    }));
    document.addEventListener('change', e => { if (e.target.classList.contains('perm')) actualizar(); });
    actualizar();
})();
</script>
