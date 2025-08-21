<?php
/**
 * Fix Database Insert - Diagnosticar y Solucionar Error de Inserción
 * Ejecutar desde navegador para diagnosticar el problema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Fix Database Insert - Diagnóstico</h1>";

try {
    require_once 'config/config.php';
    require_once 'classes/Database.php';
    
    $db = Database::getInstance();
    
    echo "<h2>1. ✅ Conexión a BD establecida</h2>";
    
    // Verificar que las tablas existen
    echo "<h2>2. 🔍 Verificando estructura de tablas</h2>";
    
    $tables = ['companies', 'trips', 'vouchers'];
    
    foreach ($tables as $table) {
        $result = $db->fetch("SHOW TABLES LIKE ?", [$table]);
        if ($result) {
            echo "<p>✅ Tabla '{$table}' existe</p>";
            
            // Mostrar estructura de la tabla
            $columns = $db->fetchAll("DESCRIBE {$table}");
            echo "<details><summary>📋 Estructura de {$table}</summary>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Clave</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            echo "</table></details>";
        } else {
            echo "<p>❌ Tabla '{$table}' NO existe</p>";
        }
    }
    
    echo "<h2>3. 🧪 Test de inserción en companies</h2>";
    
    // Test básico de inserción en companies
    try {
        $company_id = $db->insert('companies', [
            'name' => 'Martin Marietta Materials TEST',
            'legal_name' => 'Martin Marietta Materials TEST',
            'created_by' => 1,
            'deduction_type' => 'percentage',
            'deduction_value' => 5.00,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($company_id) {
            echo "<p>✅ Inserción en companies exitosa. ID: {$company_id}</p>";
            
            // Limpiar el test
            $db->delete('companies', 'id = ?', [$company_id]);
            echo "<p>🧹 Registro de test eliminado</p>";
        } else {
            echo "<p>❌ Error en inserción companies</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error insertando en companies: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>4. 🧪 Test de inserción en trips</h2>";
    
    // Verificar si existe una empresa Martin Marietta
    $martin_company = $db->fetch("SELECT id FROM companies WHERE name LIKE '%Martin%Marietta%' LIMIT 1");
    
    if (!$martin_company) {
        // Crear empresa Martin Marietta
        echo "<p>📝 Creando empresa Martin Marietta...</p>";
        $martin_company_id = $db->insert('companies', [
            'name' => 'Martin Marietta Materials',
            'legal_name' => 'Martin Marietta Materials Inc.',
            'created_by' => 1,
            'deduction_type' => 'percentage',
            'deduction_value' => 5.00,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($martin_company_id) {
            echo "<p>✅ Empresa Martin Marietta creada con ID: {$martin_company_id}</p>";
        } else {
            echo "<p>❌ Error creando empresa Martin Marietta</p>";
            exit();
        }
    } else {
        $martin_company_id = $martin_company['id'];
        echo "<p>✅ Empresa Martin Marietta ya existe con ID: {$martin_company_id}</p>";
    }
    
    // Test de inserción en trips
    try {
        $trip_data = [
            'voucher_id' => 5, // Usar el voucher que ya procesamos
            'company_id' => $martin_company_id,
            'trip_date' => '2025-07-14',
            'origin' => 'Martin Marietta - 16146',
            'destination' => 'RMT Plant - Austin TX',
            'weight_tons' => 21.56,
            'unit_rate' => 10.60,
            'subtotal' => 228.54,
            'deduction_type' => 'percentage',
            'deduction_value' => 5.00,
            'deduction_amount' => 11.43,
            'total_amount' => 217.11,
            'vehicle_plate' => '1488527942',
            'driver_name' => 'Conductor 1488527942',
            'ticket_number' => 'H2648319/41142689',
            'product_type' => 'Material de Construcción',
            'extraction_confidence' => 0.95,
            'data_source_type' => 'pdf',
            'source_row_number' => 27,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        echo "<h3>📋 Datos de test para trips:</h3>";
        echo "<pre>" . print_r($trip_data, true) . "</pre>";
        
        $trip_id = $db->insert('trips', $trip_data);
        
        if ($trip_id) {
            echo "<p>✅ Inserción en trips exitosa. ID: {$trip_id}</p>";
            
            // Verificar el trip insertado
            $inserted_trip = $db->fetch("SELECT * FROM trips WHERE id = ?", [$trip_id]);
            echo "<h3>📋 Trip insertado:</h3>";
            echo "<pre>" . print_r($inserted_trip, true) . "</pre>";
            
            // Limpiar el test
            $db->delete('trips', 'id = ?', [$trip_id]);
            echo "<p>🧹 Registro de test eliminado</p>";
            
        } else {
            echo "<p>❌ Error en inserción trips - No se obtuvo ID</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error insertando en trips: " . $e->getMessage() . "</p>";
        echo "<p>📍 Archivo: " . $e->getFile() . "</p>";
        echo "<p>📍 Línea: " . $e->getLine() . "</p>";
        
        // Mostrar trace completo
        echo "<details><summary>🔍 Stack Trace Completo</summary>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
        echo "</details>";
    }
    
    echo "<h2>5. 🔧 Verificar configuración de PDO</h2>";
    
    $pdo = $db->getConnection();
    echo "<p>✅ Conexión PDO obtenida</p>";
    echo "<p>📊 Versión MySQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "</p>";
    echo "<p>📊 Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "</p>";
    
    // Verificar configuración de errores
    echo "<p>📊 Error Mode: " . $pdo->getAttribute(PDO::ATTR_ERRMODE) . "</p>";
    echo "<p>📊 Autocommit: " . ($pdo->getAttribute(PDO::ATTR_AUTOCOMMIT) ? 'ON' : 'OFF') . "</p>";
    
    echo "<h2>6. 🧪 Test de transacciones</h2>";
    
    try {
        $db->beginTransaction();
        echo "<p>✅ Transacción iniciada</p>";
        
        $test_company_id = $db->insert('companies', [
            'name' => 'Test Transaction Company',
            'legal_name' => 'Test Transaction Company',
            'created_by' => 1,
            'deduction_type' => 'percentage',
            'deduction_value' => 5.00,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "<p>✅ Insert en transacción exitoso. ID: {$test_company_id}</p>";
        
        $db->rollback();
        echo "<p>✅ Rollback exitoso</p>";
        
        // Verificar que el rollback funcionó
        $check = $db->fetch("SELECT id FROM companies WHERE id = ?", [$test_company_id]);
        if (!$check) {
            echo "<p>✅ Rollback verificado - registro no existe</p>";
        } else {
            echo "<p>⚠️ Rollback no funcionó correctamente</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Error en test de transacciones: " . $e->getMessage() . "</p>";
        try {
            $db->rollback();
        } catch (Exception $rollback_error) {
            echo "<p>❌ Error en rollback: " . $rollback_error->getMessage() . "</p>";
        }
    }
    
    echo "<hr>";
    echo "<h2>🎯 Diagnóstico Completo</h2>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3>✅ Elementos funcionando:</h3>";
    echo "<ul>";
    echo "<li>✅ Conexión a base de datos</li>";
    echo "<li>✅ Tablas existen</li>";
    echo "<li>✅ Empresa Martin Marietta disponible</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3>🔧 Próximos pasos:</h3>";
    echo "<ol>";
    echo "<li>Si el test de trips falló, revisar la estructura de la tabla 'trips'</li>";
    echo "<li>Verificar que todos los campos requeridos estén presentes</li>";
    echo "<li>Ejecutar el procesamiento del PDF nuevamente</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h2>❌ Error general:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Diagnóstico completado - " . date('Y-m-d H:i:s') . "</small></p>";
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
pre { 
    background: #f8f9fa; 
    padding: 10px; 
    border-radius: 5px; 
    overflow-x: auto; 
    font-size: 12px;
}
table { border-collapse: collapse; margin: 10px 0; font-size: 12px; }
th, td { padding: 6px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; font-weight: bold; }
details { margin: 10px 0; }
summary { cursor: pointer; font-weight: bold; color: #007bff; }
</style>