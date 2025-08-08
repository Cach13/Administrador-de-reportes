<?php
/**
 * Logout - Cerrar sesión del usuario
 */

session_start();

// Incluir la clase Auth para logout seguro
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Auth.php';

$auth = new Auth();

// Ejecutar logout (la clase Auth ya maneja el logging internamente)
$auth->logout();

// Redirigir al login con mensaje
header('Location: login.php?logout=success');
exit();
?>