<?php
session_start();
date_default_timezone_set('America/Mazatlan');

define('DB_HOST', 'localhost');
define('DB_NAME', 'gpndorbywu_supermm_syso');
define('DB_USER', 'gpndorbywu_supermm');
define('DB_PASS', '#Elkiwizon123');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

define('BASE_URL', '/');
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