<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Verificar si el usuario sigue activo y obtener sus datos
$stmt = $pdo->prepare("SELECT activo, nombre_completo, rol, sucursal_id, password_change_required, password_last_change 
                       FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

if (!$usuario || $usuario['activo'] != 1) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?error=inactivo');
    exit;
}

// Variables globales
$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $usuario['nombre_completo'];
$usuario_rol = $usuario['rol'];
$usuario_sucursal_id = $usuario['sucursal_id'];

// Si es supervisor, forzar sucursal_id en GET/POST
if ($usuario_rol === 'supervisor' && $usuario_sucursal_id) {
    $_GET['sucursal_id'] = $usuario_sucursal_id;
    $_POST['sucursal_id'] = $usuario_sucursal_id;
}

// --- Verificar si debe cambiar contraseña (primer login o expiración) ---
$debe_cambiar = $usuario['password_change_required'] == 1;

if (!$debe_cambiar && PASSWORD_EXPIRA_DIAS > 0 && $usuario['password_last_change']) {
    // Verificar si el rol del usuario está en la lista de roles a los que se aplica expiración
    $roles_expiran = array_map('trim', explode(',', PASSWORD_EXPIRACION_ROLES));
    if (in_array($usuario_rol, $roles_expiran)) {
        $ultimo_cambio = new DateTime($usuario['password_last_change']);
        $hoy = new DateTime();
        $diferencia = $hoy->diff($ultimo_cambio)->days;
        if ($diferencia >= PASSWORD_EXPIRA_DIAS) {
            $debe_cambiar = true;
            // Marcar en BD para forzar cambio
            $pdo->prepare("UPDATE usuarios SET password_change_required = 1 WHERE id = ?")->execute([$usuario_id]);
        }
    }
}

// Si debe cambiar y no está ya en cambiar_password.php, redirigir
$pagina_actual = basename($_SERVER['PHP_SELF']);
if ($debe_cambiar && $pagina_actual !== 'cambiar_password.php') {
    header('Location: ' . BASE_URL . 'cambiar_password.php');
    exit;
}
?>