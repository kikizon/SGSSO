<?php
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requieren email y password.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, nombre_completo, password_hash, rol FROM usuarios WHERE email = ? AND activo = 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales inválidas.']);
    exit;
}

// Generar token
$token = bin2hex(random_bytes(32));
$descripcion = $input['descripcion'] ?? 'API Access';

$stmt = $pdo->prepare("INSERT INTO api_tokens (usuario_id, token, descripcion) VALUES (?, ?, ?)");
$stmt->execute([$user['id'], $token, $descripcion]);

http_response_code(200);
echo json_encode([
    'success' => true,
    'token'   => $token,
    'usuario' => [
        'id'     => $user['id'],
        'nombre' => $user['nombre_completo'],
        'rol'    => $user['rol']
    ]
]);
exit;