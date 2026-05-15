<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT id, nombre_completo, password_hash, rol, sucursal_id FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre_completo'];
            $_SESSION['usuario_rol'] = $user['rol'];
            $_SESSION['usuario_sucursal_id'] = $user['sucursal_id'];
            
            registrar_auditoria($pdo, $user['id'], 'LOGIN', 'usuarios', $user['id'], 'Inicio de sesión exitoso');

            header('Location: modules/dashboard/');
            exit;
        } else {
            $error = 'Credenciales incorrectas o usuario inactivo.';
        }
    } else {
        $error = 'Por favor complete todos los campos.';
    }
}

// Verificar si existe el logo, si no usar un placeholder
$logoPath = 'assets/img/logo.png';
$logoExists = file_exists($logoPath);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión · SUPERMM SYSO</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0d6efd 0%, #0099ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            padding: 20px;
        }
        .login-card {
            max-width: 1000px;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            background: white;
        }
        .login-left {
            background: linear-gradient(145deg, #f8fafc 0%, #e9ecef 100%);
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .login-left img {
            max-width: 180px;
            margin-bottom: 25px;
        }
        .login-left h3 {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .login-left p {
            color: #64748b;
            font-size: 0.95rem;
        }
        .login-right {
            padding: 50px 40px;
            background: white;
        }
        .login-right .logo-mobile {
            display: none;
            text-align: center;
            margin-bottom: 20px;
        }
        .login-right h4 {
            font-weight: 600;
            color: #0b2b4a;
            margin-bottom: 10px;
        }
        .login-right .form-label {
            font-weight: 500;
            color: #334155;
        }
        .input-group-text {
            background: transparent;
            border-right: none;
            color: #64748b;
        }
        .form-control {
            border-left: none;
            padding-left: 0;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
        .input-group:focus-within .input-group-text {
            border-color: #0d6efd;
        }
        .btn-login {
            background: linear-gradient(145deg, #0d6efd 0%, #0b5ed7 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(13,110,253,0.3);
        }
        .footer-note {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 20px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .login-left {
                display: none;
            }
            .login-right .logo-mobile {
                display: block;
            }
            .login-right {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="row g-0">
            <!-- Panel Izquierdo: Logo y mensaje -->
            <div class="col-md-5 login-left">
                <?php if ($logoExists): ?>
                    <img src="<?= BASE_URL . $logoPath ?>" alt="SUPERMM Logo">
                <?php else: ?>
                    <div style="font-size: 4rem; color: #0d6efd; margin-bottom: 20px;">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                <?php endif; ?>
                <h3>SUPERMM SYSO</h3>
                <p>Sistema de Gestión de Seguridad y Salud Ocupacional</p>
                <div style="margin-top: 30px; font-size: 0.85rem; color: #6c757d;">
                    <i class="fas fa-shield-alt"></i> Acceso seguro · v1.0
                </div>
            </div>
            
            <!-- Panel Derecho: Formulario -->
            <div class="col-md-7 login-right">
                <div class="logo-mobile">
                    <?php if ($logoExists): ?>
                        <img src="<?= BASE_URL . $logoPath ?>" alt="Logo" style="max-width: 120px;">
                    <?php else: ?>
                        <i class="fas fa-hard-hat" style="font-size: 3rem; color: #0d6efd;"></i>
                    <?php endif; ?>
                </div>
                <h4>¡Bienvenido de nuevo!</h4>
                <p class="text-muted mb-4">Ingresa tus credenciales para continuar</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="ejemplo@supermm.mx" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-login w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                    </button>
                </form>
                <div class="footer-note">
                    <i class="far fa-copyright"></i> SUPERMM · SYSO <?= date('Y') ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS (opcional para interactividad, no requerido aquí) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>