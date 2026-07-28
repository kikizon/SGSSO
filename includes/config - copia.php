<?php
// --- Endurecer la cookie de sesión (antes de iniciarla) ---
$cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime'  => 0,
    'path'      => '/',
    'httponly'  => true,
    'secure'    => $cookieSecure,
    'samesite'  => 'Lax',
]);
session_start();
date_default_timezone_set('America/Mazatlan');

define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DB connect: ' . $e->getMessage());
    die("Error de conexión con la base de datos.");
}

define('BASE_URL', '/');
// Fuente de los permisos: 'bd' (tablas de roles) | 'mapa' (compatibilidad por rol)
define('PERMISOS_FUENTE', 'bd');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

// --- Cargar configuración desde BD (modelo clave-valor) ---
if (!isset($_SESSION['config'])) {
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion");
    $config = [];
    while ($row = $stmt->fetch()) {
        $config[$row['clave']] = $row['valor'];
    }
    $_SESSION['config'] = $config;
}
$config = $_SESSION['config'];
define('PASSWORD_EXPIRA_DIAS', (int)($config['password_expira_dias'] ?? 90));
define('HORAS_HOMBRE_MES', (int)($config['horas_hombre_mes'] ?? 0));
define('PASSWORD_EXPIRACION_ROLES', $config['password_expiracion_roles'] ?? 'admin,supervisor,usuario');
?>
