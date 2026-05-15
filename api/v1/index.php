<?php
// Headers CORS y tipo de contenido
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Si es preflight OPTIONS, terminar
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../includes/config.php';

// Obtener la ruta solicitada
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/SYSO/api/v1/';               // ajusta según tu instalación
$endpoint = str_replace($base_path, '', parse_url($request_uri, PHP_URL_PATH));
$endpoint = trim($endpoint, '/');

// Segmentos de la URL
$segments = explode('/', $endpoint);
$recurso = $segments[0] ?? '';
$id = $segments[1] ?? null;
$accion = $segments[2] ?? null;

// Enrutamiento
switch ($recurso) {
    case 'empleados':
        require_once 'empleados.php';
        break;
    case 'reportes':
        require_once 'reportes.php';
        break;
    case 'dashboard':
        require_once 'dashboard.php';
        break;
    case 'catalogos':
        require_once 'catalogos.php';
        break;
    case 'auth':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once 'auth.php';
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido. Use POST.']);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint no encontrado.']);
}
exit;