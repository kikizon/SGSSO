<?php
// Redirige al login o al dashboard si ya hay sesión
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: modules/dashboard/');
} else {
    header('Location: login.php');
}
exit;