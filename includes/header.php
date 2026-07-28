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

// Salvaguarda: el menú usa puede(); si aún no está cargado el motor, se carga.
if (!function_exists('puede')) { require_once __DIR__ . '/permisos.php'; }

// Contador de autorizaciones pendientes
$autz_pendientes = 0;
if (function_exists('puede') && puede('autorizaciones.aprobar') && file_exists(__DIR__ . '/authorization.php')) {
    require_once __DIR__ . '/authorization.php';
    $autz_pendientes = autz_contar_pendientes($pdo);
}
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

    <link rel="manifest" href="<?= BASE_URL ?>manifest.webmanifest">
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SYSO">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/pwa/apple-touch-icon.png">
    <link rel="icon" href="<?= BASE_URL ?>assets/pwa/favicon-32.png" sizes="32x32">
    
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

                <?php if (puede('incapacidades.ver')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>modules/incapacidades/listar.php">
                        <i class="fas fa-user-injured"></i> Incapacidades
                    </a>
                </li>
                <?php endif; ?>

                <?php if (puede('autorizaciones.ver')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>modules/autorizaciones/listar.php">
                        <i class="fas fa-user-shield"></i> Autorizaciones
                        <?php if (puede('autorizaciones.aprobar') && $autz_pendientes > 0): ?>
                            <span class="badge bg-danger"><?= $autz_pendientes ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (puede_alguno(['reportes.ver','reportes.crear'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-exclamation-triangle"></i> Reportes
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (puede('reportes.ver')): ?>
                        <li><h6 class="dropdown-header">Ver Reportes</h6></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/listar.php?tipo=acto_inseguro"><i class="fas fa-skull-crossbones"></i> Actos Inseguros</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/listar.php?tipo=accidente"><i class="fas fa-car-crash"></i> Accidentes</a></li>
                        <?php endif; ?>
                        <?php if (puede('reportes.crear')): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Nuevo Reporte</h6></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/crear.php?tipo=acto_inseguro"><i class="fas fa-skull-crossbones"></i> Acto Inseguro</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/reportes/crear.php?tipo=accidente"><i class="fas fa-car-crash"></i> Accidente</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (puede_alguno(['6s.ver','6s.realizar','6s.tendencias','6s.resumen'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-clipboard-check"></i> 6S
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (puede('6s.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/auditoria6s/listar.php"><i class="fas fa-list"></i> Auditorías</a></li>
                        <?php endif; ?>
                        <?php if (puede('6s.realizar')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/auditoria6s/realizar.php"><i class="fas fa-plus"></i> Nueva auditoría</a></li>
                        <?php endif; ?>
                        <?php if (puede('6s.tendencias')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/auditoria6s/tendencias.php"><i class="fas fa-chart-line"></i> Tendencias</a></li>
                        <?php endif; ?>
                        <?php if (puede('6s.resumen')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/auditoria6s/resumen.php"><i class="fas fa-clipboard-list"></i> Hoja de resumen</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (puede_alguno(['departamentos.ver','sucursales.ver','empleados.ver','actos.ver','tipos_accidente.ver','atenciones_medicas.ver','enfermedades.ver','alergias.ver','cursos.ver','6s.criterios.gestionar','bitacora.ver','configuracion.ver','usuarios.ver','roles.gestionar','papelera.ver'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-database"></i> Catálogos
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (puede('departamentos.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/departamentos/listar.php"><i class="fas fa-building"></i> Departamentos</a></li>
                        <?php endif; ?>
                        <?php if (puede('sucursales.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/sucursales/listar.php"><i class="fas fa-store"></i> Sucursales</a></li>
                        <?php endif; ?>
                        <?php if (puede('empleados.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/empleados/listar.php"><i class="fas fa-users"></i> Empleados</a></li>
                        <?php endif; ?>
                        <?php if (puede('actos.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/actos/listar.php"><i class="fas fa-skull-crossbones"></i> Actos Inseguros</a></li>
                        <?php endif; ?>
                        <?php if (puede('tipos_accidente.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/tipos_accidente/listar.php"><i class="fas fa-car-crash"></i> Tipos de Accidente</a></li>
                        <?php endif; ?>
                        <?php if (puede('atenciones_medicas.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/atenciones_medicas/listar.php"><i class="fas fa-hospital"></i> Atenciones Médicas</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (puede('enfermedades.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/enfermedades_cronicas/listar.php"><i class="fas fa-heartbeat"></i> Enfermedades Crónicas</a></li>
                        <?php endif; ?>
                        <?php if (puede('alergias.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/alergias/listar.php"><i class="fas fa-allergies"></i> Alergias</a></li>
                        <?php endif; ?>
                        <?php if (puede('cursos.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/cursos/listar.php"><i class="fas fa-chalkboard-teacher"></i> Cursos/Formatos</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (puede('6s.criterios.gestionar')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/auditoria6s/admin_criterios.php"><i class="fas fa-tasks"></i> Criterios 6S</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (puede('bitacora.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/auditoria/listar.php"><i class="fas fa-history"></i> Auditoría</a></li>
                        <?php endif; ?>
                        <?php if (puede('configuracion.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/configuracion/listar.php"><i class="fas fa-cog"></i> Configuración</a></li>
                        <?php endif; ?>
                        <?php if (puede('usuarios.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/usuarios/listar.php"><i class="fas fa-user-cog"></i> Usuarios</a></li>
                        <?php endif; ?>
                        <?php if (puede('roles.gestionar')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/roles/listar.php"><i class="fas fa-user-shield"></i> Roles y permisos</a></li>
                        <?php endif; ?>
                        <?php if (puede('papelera.ver')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/papelera/listar.php"><i class="fas fa-trash-restore"></i> Papelera</a></li>
                        <?php endif; ?>
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