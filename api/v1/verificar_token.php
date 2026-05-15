<?php
function verificarToken($pdo) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token no proporcionado.']);
        exit;
    }

    $token = $matches[1];

    $stmt = $pdo->prepare("SELECT t.*, u.nombre_completo, u.rol, u.sucursal_id 
                           FROM api_tokens t 
                           JOIN usuarios u ON t.usuario_id = u.id 
                           WHERE t.token = ? AND t.activo = 1 AND u.activo = 1");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        http_response_code(403);
        echo json_encode(['error' => 'Token inválido o expirado.']);
        exit;
    }

    // Actualizar último uso
    $pdo->prepare("UPDATE api_tokens SET ultimo_uso = NOW() WHERE token = ?")->execute([$token]);

    return $tokenData;
}