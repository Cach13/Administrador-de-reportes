<?php
/**
 * Verificar Estructura de BD - Ver qué columnas tienes realmente
 * Ejecutar desde: /check-db.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Verificación de Estructura BD</h1>";

try {
    require_once 'config/config.php';
    require_once 'classes/Database.php';
    
    $db = Database::getInstance();
    
    echo "<h2>✅ Conexión establecida</h2>";
    
    // Verificar tabla vouchers
    echo "<h3>📋 Tabla VOUCHERS</h3>";
    $vouchers_columns = $db->fetchAll("DESCRIBE vouchers");
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Columna</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($vouchers_columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar tabla trips
    echo "<h3>🚚 Tabla TRIPS</h3>";
    $trips_exist = $db->fetchAll("SHOW TABLES LIKE 'trips'");
    if ($trips_exist) {
        $trips_columns = $db->fetchAll("DESCRIBE trips");
        echo "<p>✅ Tabla trips existe con " . count($trips_columns) . " columnas</p>";
        
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Columna</th><th>Tipo</th></tr>";
        foreach ($trips_columns as $col) {
            echo "<tr><td><strong>" . $col['Field'] . "</strong></td><td>" . $col['Type'] . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ Tabla trips NO existe</p>";
    }
    
    // Verificar tabla companies
    echo "<h3>🏢 Tabla COMPANIES</h3>";
    $companies_exist = $db->fetchAll("SHOW TABLES LIKE 'companies'");
    if ($companies_exist) {
        $companies_count = $db->fetch("SELECT COUNT(*) as count FROM companies");
        echo "<p>✅ Tabla companies existe con " . $companies_count['count'] . " registros</p>";
        
        $companies = $db->fetchAll("SELECT name, identifier FROM companies LIMIT 5");
        if ($companies) {
            echo "<p><strong>Empresas ejemplo:</strong></p>";
            foreach ($companies as $company) {
                echo "- " . $company['name'] . " (" . $company['identifier'] . ")<br>";
            }
        }
    } else {
        echo "<p>❌ Tabla companies NO existe</p>";
    }
    
    // Verificar datos en vouchers
    echo "<h3>📄 Datos en VOUCHERS</h3>";
    $vouchers_count = $db->fetch("SELECT COUNT(*) as count FROM vouchers");
    echo "<p>Total vouchers: " . $vouchers_count['count'] . "</p>";
    
    if ($vouchers_count['count'] > 0) {
        $sample_vouchers = $db->fetchAll("SELECT * FROM vouchers LIMIT 3");
        echo "<p><strong>Vouchers ejemplo:</strong></p>";
        foreach ($sample_vouchers as $voucher) {
            echo "- " . $voucher['voucher_number'] . " (" . $voucher['status'] . ")<br>";
        }
    }
    
    // Mostrar SQL correcto
    echo "<h3>🔧 SQL CORRECTO para tu BD</h3>";
    $column_names = array_column($vouchers_columns, 'Field');
    echo "<p><strong>Columnas disponibles:</strong> " . implode(', ', $column_names) . "</p>";
    
    echo "<div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<pre>";
    echo "SELECT \n";
    echo "    v.id,\n";
    echo "    v.voucher_number,\n";
    echo "    v.original_filename,\n";
    echo "    v.status,\n";
    echo "    v.upload_date,\n";
    if (in_array('uploaded_by', $column_names)) {
        echo "    v.uploaded_by,\n";
    }
    echo "    u.full_name as uploaded_by_name\n";
    echo "FROM vouchers v\n";
    echo "LEFT JOIN users u ON v.uploaded_by = u.id\n";
    echo "ORDER BY v.upload_date DESC";
    echo "</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>