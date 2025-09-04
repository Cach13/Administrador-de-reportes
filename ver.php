<?php
/**
 * Reset Admin Simple - SIN columnas problemáticas
 * Ejecutar desde navegador para resetear credenciales de forma segura
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Reset Admin - Versión Simple</h1>";

try {
    require_once 'config/config.php';
    require_once 'classes/Database.php';
    
    $db = Database::getInstance();
    
    echo "<h2>1. ✅ Conexión a BD establecida</h2>";
    
    // Primero, ver qué columnas REALMENTE existen en users
    echo "<h2>2. 🔍 Verificando columnas disponibles en tabla USERS</h2>";
    
    $user_columns = $db->fetchAll("DESCRIBE users");
    $available_columns = array_column($user_columns, 'Field');
    
    echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo "<p><strong>Columnas disponibles:</strong> " . implode(', ', $available_columns) . "</p>";
    echo "</div>";
    
    // Nuevas credenciales
    $admin_username = 'admin';
    $admin_password = 'admin123';
    $admin_email = 'admin@transport.com';
    $admin_fullname = 'Administrador del Sistema';
    
    // Hash de la contraseña
    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    
    echo "<h2>3. 🔐 Configurando nuevas credenciales</h2>";
    echo "<div style='background: #e7f5e7; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<p><strong>👤 Usuario:</strong> {$admin_username}</p>";
    echo "<p><strong>🔑 Contraseña:</strong> {$admin_password}</p>";
    echo "<p><strong>📧 Email:</strong> {$admin_email}</p>";
    echo "</div>";
    
    // Verificar si existe usuario admin
    $existing_admin = $db->fetch("SELECT id, username FROM users WHERE username = ?", [$admin_username]);
    
    if ($existing_admin) {
        echo "<h2>4. 🔄 Actualizando usuario admin existente (ID: {$existing_admin['id']})</h2>";
        
        // UPDATE usando solo columnas que SÍ existen
        $update_data = [
            'password_hash' => $password_hash,
            'full_name' => $admin_fullname,
            'role' => 'admin',
            'is_active' => 1
        ];
        
        // Agregar email solo si la columna existe
        if (in_array('email', $available_columns)) {
            $update_data['email'] = $admin_email;
        }
        
        echo "<p>📋 Datos a actualizar: " . implode(', ', array_keys($update_data)) . "</p>";
        
        // Usar query manual para evitar problemas con Database.php
        $sql = "UPDATE users SET ";
        $fields = [];
        $values = [];
        
        foreach ($update_data as $field => $value) {
            $fields[] = "{$field} = ?";
            $values[] = $value;
        }
        
        $sql .= implode(', ', $fields) . " WHERE id = ?";
        $values[] = $existing_admin['id'];
        
        echo "<p><strong>SQL:</strong> " . $sql . "</p>";
        
        $stmt = $db->query($sql, $values);
        
        if ($stmt->rowCount() > 0) {
            echo "<p>✅ Usuario admin actualizado correctamente</p>";
            $admin_id = $existing_admin['id'];
        } else {
            echo "<p>⚠️ Usuario admin ya tenía los datos correctos</p>";
            $admin_id = $existing_admin['id'];
        }
        
    } else {
        echo "<h2>4. ➕ Creando nuevo usuario admin</h2>";
        
        // INSERT usando solo columnas básicas
        $insert_data = [
            'username' => $admin_username,
            'password_hash' => $password_hash,
            'full_name' => $admin_fullname,
            'role' => 'admin',
            'is_active' => 1
        ];
        
        // Agregar columnas opcionales si existen
        if (in_array('email', $available_columns)) {
            $insert_data['email'] = $admin_email;
        }
        
        if (in_array('created_at', $available_columns)) {
            $insert_data['created_at'] = date('Y-m-d H:i:s');
        }
        
        echo "<p>📋 Datos a insertar: " . implode(', ', array_keys($insert_data)) . "</p>";
        
        // Insert manual
        $columns = implode(', ', array_keys($insert_data));
        $placeholders = implode(', ', array_fill(0, count($insert_data), '?'));
        $sql = "INSERT INTO users ({$columns}) VALUES ({$placeholders})";
        
        echo "<p><strong>SQL:</strong> " . $sql . "</p>";
        
        $stmt = $db->query($sql, array_values($insert_data));
        $admin_id = $db->lastInsertId();
        
        if ($admin_id) {
            echo "<p>✅ Usuario admin creado correctamente con ID: {$admin_id}</p>";
        } else {
            echo "<p>❌ Error creando usuario admin</p>";
        }
    }
    
    echo "<h2>5. 🧪 Test de login</h2>";
    
    // Test de login con las nuevas credenciales
    if (isset($admin_id)) {
        try {
            require_once 'classes/Auth.php';
            $auth = new Auth();
            
            $login_result = $auth->login($admin_username, $admin_password);
            
            if ($login_result['success']) {
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                echo "<h3>🎉 ¡LOGIN EXITOSO!</h3>";
                echo "<p>✅ Las credenciales funcionan perfectamente</p>";
                echo "<p><strong>Usuario:</strong> {$login_result['user']['username']}</p>";
                echo "<p><strong>Nombre:</strong> {$login_result['user']['full_name']}</p>";
                echo "<p><strong>Rol:</strong> {$login_result['user']['role']}</p>";
                echo "<p><strong>ID:</strong> {$login_result['user']['id']}</p>";
                echo "</div>";
                
                // Logout del test
                $auth->logout();
                echo "<p>🚪 Logout de test realizado</p>";
                
            } else {
                echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
                echo "<h3>❌ LOGIN FALLÓ</h3>";
                echo "<p><strong>Error:</strong> {$login_result['message']}</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<p>❌ Error en test de login: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>6. 👥 Verificar usuario final</h2>";
    
    // Verificar el usuario final
    $final_user = $db->fetch("SELECT id, username, full_name, role, is_active FROM users WHERE username = ?", [$admin_username]);
    
    if ($final_user) {
        echo "<div style='background: #cce5ff; padding: 15px; border-radius: 8px;'>";
        echo "<h3>👤 Usuario Admin Final:</h3>";
        echo "<p><strong>ID:</strong> {$final_user['id']}</p>";
        echo "<p><strong>Username:</strong> {$final_user['username']}</p>";
        echo "<p><strong>Nombre:</strong> {$final_user['full_name']}</p>";
        echo "<p><strong>Rol:</strong> {$final_user['role']}</p>";
        echo "<p><strong>Activo:</strong> " . ($final_user['is_active'] ? 'SÍ' : 'NO') . "</p>";
        echo "</div>";
    }
    
    echo "<h2>7. 🔑 Verificar hash de contraseña</h2>";
    
    // Verificar que el hash funciona
    $hash_check = $db->fetchValue("SELECT password_hash FROM users WHERE username = ?", [$admin_username]);
    
    if ($hash_check) {
        $hash_valid = password_verify($admin_password, $hash_check);
        echo "<p><strong>Hash actual:</strong> " . substr($hash_check, 0, 50) . "...</p>";
        echo "<p><strong>Hash válido:</strong> " . ($hash_valid ? '✅ SÍ' : '❌ NO') . "</p>";
        
        if ($hash_valid) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
            echo "<p>✅ La contraseña '<strong>{$admin_password}</strong>' es correcta para el usuario '<strong>{$admin_username}</strong>'</p>";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px;'>";
    echo "<h2>❌ Error Fatal</h2>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 12px;'>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>🎯 RESULTADO FINAL</h2>";
echo "<h3>🔐 Credenciales para usar:</h3>";
echo "<p><strong>👤 Usuario:</strong> admin</p>";
echo "<p><strong>🔑 Contraseña:</strong> admin123</p>";
echo "<h3>🌐 Ir al login:</h3>";
echo "<p><a href='login.php' style='background: #dc2626; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 IR AL LOGIN</a></p>";
echo "</div>";

echo "<p><small>Reset simple completado - " . date('Y-m-d H:i:s') . "</small></p>";
?>