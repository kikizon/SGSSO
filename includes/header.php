<?php
if (!isset($pdo))
    require_once __DIR__ . '/config.php';

if (!isset($usuario_nombre) && isset($_SESSION['usuario_nombre'])) 
{
    $usuario_nombre = $_SESSION['usuario_nombre'];
    $usuario_rol = $_SESSION['usuario_rol'];
    $usuario_sucursal_id = $_SESSION['usuario_sucursal_id'] ?? null;
}

$logoPath = 'assets/img/logo.png';
$logoExists = file_exists(__DIR__ . '/../' . $logoPath);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SUPERMM - SYSO</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/custom.css">
    <style>
        .navbar-brand img {
            height: 35px;
            margin-right: 8px;
        }
        @media (max-width: 576px) {
            .navbar-brand img {
                height: 28px;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>modules/dashboard/">
            <?php if ($logoExists): ?>
                <img src="<?= BASE_URL . $logoPath ?>" alt="SUPERMM Logo">
            <?php else: ?>
                <i class="fas fa-hard-hat me-2"></i>
            <?php endif; ?>
            SUPERMM SYSO
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>modules/dashboard/"><i class="fas fa-chart-pie"></i> Dashboard</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-exclamation-triangle"></i> Reportes
                    </a>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">Ver Reportes</h6></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/listar.php?tipo=acto_inseguro"><i class="fas fa-skull-crossbones"></i> Actos Inseguros</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/listar.php?tipo=accidente"><i class="fas fa-car-crash"></i> Accidentes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Nuevo Reporte</h6></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/crear.php?tipo=acto_inseguro"><i class="fas fa-skull-crossbones"></i> Acto Inseguro</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/crear.php?tipo=accidente"><i class="fas fa-car-crash"></i> Accidente</a></li>
                    </ul>
                </li>
                <?php if ($usuario_rol === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-database"></i> Catálogos
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/departamentos/listar.php"><i class="fas fa-building"></i> Departamentos</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/sucursales/listar.php"><i class="fas fa-store"></i> Sucursales</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/empleados/listar.php"><i class="fas fa-users"></i> Empleados</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/actos/listar.php"><i class="fas fa-skull-crossbones"></i> Actos Inseguros</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/tipos_accidente/listar.php"><i class="fas fa-car-crash"></i> Tipos de Accidente</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/atenciones_medicas/listar.php"><i class="fas fa-hospital"></i> Atenciones Médicas</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/enfermedades_cronicas/listar.php"><i class="fas fa-heartbeat"></i> Enfermedades Crónicas</a>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/alergias/listar.php"><i class="fas fa-allergies"></i> Alergias</a></li></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/cursos/listar.php"><i class="fas fa-chalkboard-teacher"></i> Cursos/Formatos</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/auditoria/listar.php"><i class="fas fa-history"></i> Auditoría</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/configuracion/listar.php"><i class="fas fa-cog"></i> Configuración</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/usuarios/listar.php"><i class="fas fa-user-cog"></i> Usuarios</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex text-light">
                <span class="me-3"><i class="fas fa-user"></i> <?= htmlspecialchars($usuario_nombre) ?> (<?= $usuario_rol ?>)</span>
                <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </div>
</nav>
<main class="container-fluid mt-3">