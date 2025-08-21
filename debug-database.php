<?php
/**
 * Debug Database Class - Encontrar el problema específico
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Debug Database Class</h1>";

try {
    require_once 'config/config.php';
    
    echo "<h2>1. ✅ Config cargado</h2>";
    echo "<p>DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NO DEFINIDO') . "</p>";
    echo "<p>DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NO DEFINIDO') . "</p>";
    echo "<p>DB_USER: " . (defined('DB_USER') ? DB_USER : 'NO DEFINIDO') . "</p>";
    
    echo "<h2>2. 🔍 Probando conexión PDO directa</h2>";
    
    // Test de conexión PDO sin la clase Database
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        echo "<p>✅ Conexión PDO directa exitosa</p>";
        
        // Test básico
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "<p>✅ Query básica funciona: " . $result['test'] . "</p>";
        
    } catch (PDOException $e) {
        echo "<p>❌ Error PDO directo: " . $e->getMessage() . "</p>";
        exit();
    }
    
    echo "<h2>3. 🔍 Revisando Database.php línea por línea</h2>";
    
    // Leer el archivo Database.php
    $database_file = 'classes/Database.php';
    if (file_exists($database_file)) {
        $lines = file($database_file, FILE_IGNORE_NEW_LINES);
        
        echo "<h3>📋 Líneas alrededor de la línea 54:</h3>";
        echo "<table border='1' style='border-collapse: collapse; font-family: monospace; font-size: 12px;'>";
        echo "<tr><th>Línea</th><th>Código</th></tr>";
        
        for ($i = 45; $i <= 65; $i++) {
            if (isset($lines[$i-1])) {
                $line_number = $i;
                $code = htmlspecialchars($lines[$i-1]);
                $style = ($i == 54) ? "background-color: #ffebee; font-weight: bold;" : "";
                echo "<tr style='{$style}'><td>{$line_number}</td><td>{$code}</td></tr>";
            }
        }
        echo "</table>";
        
        echo "<h3>🔍 Línea 54 específicamente:</h3>";
        if (isset($lines[53])) {
            echo "<div style='background: #ffebee; padding: 10px; border-left: 4px solid #f44336;'>";
            echo "<strong>Línea 54:</strong> <code>" . htmlspecialchars($lines[53]) . "</code>";
            echo "</div>";
        }
        
    } else {
        echo "<p>❌ No se encuentra el archivo Database.php</p>";
    }
    
    echo "<h2>4. 🧪 Test manual del método problemático</h2>";
    
    // Intentar replicar exactamente lo que hace Database.php línea 54
    // Basándome en el archivo que tienes, línea 54 probablemente es en el método query()
    
    try {
        echo "<h3>Test con query simple:</h3>";
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute(['companies']);
        $result = $stmt->fetch();
        
        if ($result) {
            echo "<p>✅ SHOW TABLES LIKE funciona correctamente</p>";
            echo "<p>Resultado: " . print_r($result, true) . "</p>";
        } else {
            echo "<p>⚠️ Tabla 'companies' no existe</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error en test manual: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>5. 🔧 Test de Database class con try/catch detallado</h2>";
    
    try {
        // Incluir Database.php con manejo de errores detallado
        require_once 'classes/Database.php';
        echo "<p>✅ Database.php incluido sin errores de sintaxis</p>";
        
        // Intentar crear instancia
        echo "<h3>Creando instancia de Database...</h3>";
        $db = Database::getInstance();
        echo "<p>✅ Instancia creada exitosamente</p>";
        
        // Test del método fetch que está fallando
        echo "<h3>Probando método fetch...</h3>";
        $result = $db->fetch("SELECT 1 as test_column");
        echo "<p>✅ Método fetch funciona: " . print_r($result, true) . "</p>";
        
        // Test con parámetros
        echo "<h3>Probando fetch con parámetros...</h3>";
        $result = $db->fetch("SELECT ? as param_test", ['hello']);
        echo "<p>✅ Fetch con parámetros funciona: " . print_r($result, true) . "</p>";
        
        // Test específico que falla
        echo "<h3>Probando query específica que falla...</h3>";
        $result = $db->fetch("SHOW TABLES LIKE ?", ['companies']);
        echo "<p>✅ Query específica funciona: " . print_r($result, true) . "</p>";
        
    } catch (Exception $e) {
        echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px;'>";
        echo "<h3>❌ Error capturado en Database class:</h3>";
        echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
        echo "<h4>Stack Trace:</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    }
    
    echo "<h2>6. 🔍 Verificar permisos y configuración MySQL</h2>";
    
    try {
        // Verificar versión de MySQL
        $version = $pdo->query("SELECT VERSION() as version")->fetch();
        echo "<p>✅ MySQL Version: " . $version['version'] . "</p>";
        
        // Verificar usuario actual
        $user = $pdo->query("SELECT USER() as current_user")->fetch();
        echo "<p>✅ Usuario actual: " . $user['current_user'] . "</p>";
        
        // Verificar base de datos actual
        $database = $pdo->query("SELECT DATABASE() as current_db")->fetch();
        echo "<p>✅ Base de datos actual: " . $database['current_db'] . "</p>";
        
        // Listar tablas disponibles
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<h3>📋 Tablas disponibles:</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>{$table}</li>";
        }
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p>❌ Error verificando configuración MySQL: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #ffebee; padding: 15px; border-radius: 8px;'>";
    echo "<h2>❌ Error general en debug:</h2>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🎯 Próximos pasos basados en los resultados:</h2>";
echo "<ol>";
echo "<li>Si la conexión PDO directa funciona pero Database.php falla, el problema está en la clase</li>";
echo "<li>Si la línea 54 es visible arriba, podremos ver exactamente qué está causando el error</li>";
echo "<li>Si las tablas no existen, necesitaremos crearlas</li>";
echo "<li>Si hay un error de sintaxis en Database.php, lo corregiremos</li>";
echo "</ol>";

echo "<p><small>Debug completado - " . date('Y-m-d H:i:s') . "</small></p>";
?>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    margin: 20px; 
    background: #f8f9fa; 
}
h1 { color: #dc3545; }
h2 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
h3 { color: #28a745; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; font-weight: bold; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px; }
code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
</style>