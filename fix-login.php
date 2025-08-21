<?php
/**
 * SCRIPT PARA ARREGLAR EL LOGIN
 * Coloca este archivo en la raíz de tu proyecto y ejecutalo
 */

// Incluir configuración de base de datos
require_once 'config/config.php';

try {
    // Conectar a la base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<h2>🔧 Fix Login Tool</h2>";
    
    // Generar hash correcto para admin123
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<p><strong>Contraseña:</strong> {$password}</p>";
    echo "<p><strong>Hash generado:</strong> {$hash}</p>";
    
    // Verificar hash
    if (password_verify($password, $hash)) {
        echo "<p style='color: green;'>✅ Hash verificado correctamente</p>";
        
        // Actualizar usuarios
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username IN ('admin', 'operador')");
        $result = $stmt->execute([$hash]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Contraseñas actualizadas correctamente</p>";
            
            // Verificar usuarios en base de datos
            $stmt = $pdo->query("SELECT username, email, full_name, role, is_active FROM users WHERE username IN ('admin', 'operador')");
            $users = $stmt->fetchAll();
            
            echo "<h3>👥 Usuarios en la base de datos:</h3>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Usuario</th><th>Email</th><th>Nombre</th><th>Rol</th><th>Activo</th></tr>";
            
            foreach ($users as $user) {
                $active = $user['is_active'] ? '✅ Sí' : '❌ No';
                echo "<tr>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['full_name']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>{$active}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Probar login
            echo "<h3>🧪 Probar Login:</h3>";
            
            // Obtener hash de admin desde BD
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
            $stmt->execute();
            $adminHash = $stmt->fetchColumn();
            
            if (password_verify('admin123', $adminHash)) {
                echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 ¡LOGIN FUNCIONARÁ! Usa:</p>";
                echo "<div style='background: #f0f8ff; padding: 15px; border: 2px solid #007bff; border-radius: 5px; margin: 10px 0;'>";
                echo "<strong>Usuario:</strong> admin<br>";
                echo "<strong>Contraseña:</strong> admin123";
                echo "</div>";
            } else {
                echo "<p style='color: red;'>❌ Aún hay un problema con el hash</p>";
                echo "<p>Hash en BD: {$adminHash}</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Error al actualizar contraseñas</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error al verificar hash</p>";
    }
    
    // Información adicional
    echo "<hr>";
    echo "<h3>📋 Información del Sistema:</h3>";
    echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
    echo "<p><strong>Password Default:</strong> " . PASSWORD_DEFAULT . "</p>";
    echo "<p><strong>Base de datos:</strong> " . DB_NAME . "</p>";
    echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error de Conexión</h2>";
    echo "<p>{$e->getMessage()}</p>";
    
    echo "<h3>🔍 Verificar Configuración:</h3>";
    echo "<p>Revisa el archivo <code>config/config.php</code> o <code>config/database.php</code></p>";
    
    if (file_exists('config/config.php')) {
        echo "<p>✅ config/config.php existe</p>";
    } else {
        echo "<p>❌ config/config.php NO existe</p>";
    }
    
    if (defined('DB_HOST')) {
        echo "<p>✅ Constantes de BD definidas</p>";
        echo "<p>Host: " . DB_HOST . "</p>";
        echo "<p>Database: " . DB_NAME . "</p>";
        echo "<p>User: " . DB_USER . "</p>";
    } else {
        echo "<p>❌ Constantes de BD NO definidas</p>";
    }
}

echo "<hr>";
echo "<p><small>Después de usar este script, puedes eliminarlo por seguridad.</small></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { width: 100%; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
code { background-color: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
</style>