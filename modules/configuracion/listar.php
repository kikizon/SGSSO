<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

if ($usuario_rol !== 'admin') {
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Obtener configuración actual
$stmt = $pdo->prepare("SELECT * FROM configuracion WHERE clave IN ('password_expira_dias', 'password_expiracion_roles', 'horas_hombre_mes')");
$stmt->execute();
$config = [];
while ($row = $stmt->fetch()) {
    $config[$row['clave']] = $row['valor'];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dias = (int)($_POST['password_expira_dias'] ?? 90);
    $horas = (int)($_POST['horas_hombre_mes'] ?? 0);
    $roles = $_POST['roles'] ?? [];
    
    // Guardar días
    $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'password_expira_dias'");
    $stmt->execute([$dias]);
    
    // Guardar horas hombre
    $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'horas_hombre_mes'");
    $stmt->execute([$horas]);
    
    // Guardar roles seleccionados
    $roles_str = implode(',', $roles);
    $stmt = $pdo->prepare("UPDATE configuracion SET valor = ? WHERE clave = 'password_expiracion_roles'");
    $stmt->execute([$roles_str]);
    
    // Actualizar sesión
    $_SESSION['config'] = [
        'password_expira_dias' => $dias,
        'horas_hombre_mes' => $horas,
        'password_expiracion_roles' => $roles_str
    ];

    registrar_auditoria($pdo, $usuario_id, 'UPDATE', 'configuracion', 1, json_encode($_POST));
    
    $success = 'Configuración guardada correctamente.';
    // Recargar valores
    $config['password_expira_dias'] = $dias;
    $config['horas_hombre_mes'] = $horas;
    $config['password_expiracion_roles'] = $roles_str;
}

$dias_actual = $config['password_expira_dias'] ?? '90';
$horas_actual = $config['horas_hombre_mes'] ?? '0';
$roles_actual = explode(',', $config['password_expiracion_roles'] ?? 'admin,supervisor,usuario');

include '../../includes/header.php';
?>

<h2><i class="fas fa-cogs"></i> Configuración del Sistema</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-key"></i> Política de Contraseñas</div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="password_expira_dias" class="form-label">Días de vigencia de la contraseña</label>
                        <input type="number" name="password_expira_dias" id="password_expira_dias" class="form-control" min="0" max="365" value="<?= htmlspecialchars($dias_actual) ?>">
                        <small class="text-muted">0 = no expira nunca. Después de este tiempo, el usuario deberá cambiar su contraseña al iniciar sesión.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Aplicar expiración a los siguientes roles:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="admin" id="rol_admin" <?= in_array('admin', $roles_actual) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="rol_admin">Administrador</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="supervisor" id="rol_supervisor" <?= in_array('supervisor', $roles_actual) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="rol_supervisor">Supervisor</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="usuario" id="rol_usuario" <?= in_array('usuario', $roles_actual) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="rol_usuario">Usuario</label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <label for="horas_hombre_mes" class="form-label">Horas Hombre Trabajadas por Mes</label>
                        <input type="number" name="horas_hombre_mes" id="horas_hombre_mes" class="form-control" min="0" value="<?= htmlspecialchars($horas_actual) ?>">
                        <small class="text-muted">Se utiliza para calcular la Tasa de Frecuencia de Accidentes.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Configuración</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Información</div>
            <div class="card-body">
                <p><strong>Caducidad de contraseñas:</strong> Obliga a los usuarios de los roles seleccionados a cambiar su contraseña después de X días. Al cumplirse el plazo, se les redirigirá automáticamente al formulario de cambio.</p>
                <p><strong>Horas Hombre:</strong> Total de horas trabajadas por todos los empleados en un mes típico. Se usa para la Tasa de Frecuencia = (Nº accidentes mes * 1.000.000) / Horas Hombre.</p>
                <p><strong>Roles:</strong> Si un rol no está seleccionado, sus contraseñas no expirarán por tiempo.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>