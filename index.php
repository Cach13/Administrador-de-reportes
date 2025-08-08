<?php
/**
 * Transport Management System
 * Punto de entrada principal - Redirige según el estado de sesión
 */

session_start();

// Incluir configuración
require_once 'config/config.php';
require_once 'config/database.php';

// Verificar si hay sesión activa
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Usuario ya logueado - ir al dashboard
    header('Location: pages/dashboard.php');
    exit();
} else {
    // Usuario no logueado - ir al splash
    header('Location: splash.php');
    exit();
}
?>