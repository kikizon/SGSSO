<?php
/**
 * Funciones auxiliares para el sistema
 */

function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

function format_date_es($date) {
    return date('d/m/Y', strtotime($date));
}

function format_datetime_es($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Registra un evento en la tabla de auditoría.
 * @param PDO $pdo Conexión a la base de datos.
 * @param int $usuario_id ID del usuario que realizó la acción.
 * @param string $accion 'INSERT', 'UPDATE' o 'DELETE'.
 * @param string $tabla Nombre de la tabla afectada.
 * @param int|null $registro_id ID del registro modificado (opcional).
 * @param string|null $detalles Información adicional en formato JSON o texto.
 */
function registrar_auditoria($pdo, $usuario_id, $accion, $tabla, $registro_id = null, $detalles = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (usuario_id, accion, tabla, registro_id, detalles, ip) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $accion, $tabla, $registro_id, $detalles, $ip]);
}
?>