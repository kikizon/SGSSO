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

// --- Multi-sucursal: lista de sucursales asignadas al usuario ---
// Fuente de verdad: tabla usuario_sucursales. Fallback: columna usuarios.sucursal_id.
$usuario_sucursales = [];
try {
    $qs = $pdo->prepare("SELECT sucursal_id FROM usuario_sucursales WHERE usuario_id = ?");
    $qs->execute([$usuario_id]);
    $usuario_sucursales = array_map('intval', array_column($qs->fetchAll(), 'sucursal_id'));
} catch (Throwable $e) {
    $usuario_sucursales = [];
}
if (empty($usuario_sucursales) && $usuario_sucursal_id) {
    $usuario_sucursales = [(int) $usuario_sucursal_id];
}
// Compatibilidad: "sucursal principal" = primera asignada (para código que aún usa $usuario_sucursal_id)
if (empty($usuario_sucursal_id) && !empty($usuario_sucursales)) {
    $usuario_sucursal_id = $usuario_sucursales[0];
}
// Cadena segura para cláusulas IN (...). Si no hay ninguna, '0' = ninguna.
$usuario_sucursales_sql = !empty($usuario_sucursales) ? implode(',', array_map('intval', $usuario_sucursales)) : '0';

// Si es supervisor con EXACTAMENTE UNA sucursal, forzar sucursal_id en GET/POST (comportamiento previo).
// Con varias sucursales no se fuerza: el scoping usa $usuario_sucursales / $usuario_sucursales_sql.
if ($usuario_rol === 'supervisor' && count($usuario_sucursales) === 1) {
    $_GET['sucursal_id'] = $usuario_sucursales[0];
    $_POST['sucursal_id'] = $usuario_sucursales[0];
}

// --- Permisos granulares del usuario ---
require_once __DIR__ . '/permisos.php';
$PERMISOS_USUARIO = permisos_cargar($pdo, (int)$usuario_id, (string)$usuario_rol);
$es_admin = ($usuario_rol === 'admin');

// --- Alcance: ¿este usuario ve TODAS las sucursales? (Fase 6) ---
// Los 73 puntos que filtran por sucursal preguntan por \$usuario_rol === 'admin'.
// A partir de aquí ese valor significa únicamente eso: alcance global.
$usuario_ve_todas_sucursales = ($usuario_rol === 'admin');
try {
    $qv = $pdo->prepare("SELECT r.es_sistema, r.ve_todas_sucursales FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?");
    $qv->execute([$usuario_id]);
    if ($rv = $qv->fetch()) {
        $usuario_ve_todas_sucursales = ((int)$rv['es_sistema'] === 1) || ((int)$rv['ve_todas_sucursales'] === 1);
    }
} catch (Throwable $e) {
    error_log('alcance de sucursales: ' . $e->getMessage());   // se conserva el valor heredado
}
if (PERMISOS_FUENTE === 'bd') {
    $usuario_rol = $usuario_ve_todas_sucursales ? 'admin' : (($usuario_rol === 'admin') ? 'usuario' : $usuario_rol);
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
