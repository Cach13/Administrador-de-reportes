<?php
/**
 * DEBUG - Archivo para diagnosticar el error de download-report.php
 * Ejecutar como: /pages/debug-download.php?id=2
 */

require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

$report_id = $_GET['id'] ?? null;

echo "<h1>🔍 DEBUG: Download Report Error</h1>";
echo "<p><strong>Report ID solicitado:</strong> {$report_id}</p>";

try {
    $db = Database::getInstance();
    
    // 1. Verificar que la tabla reports existe
    echo "<h2>1. ✅ Verificar tabla 'reports'</h2>";
    $tables = $db->fetchAll("SHOW TABLES LIKE 'reports'");
    
    if (empty($tables)) {
        echo "<p style='color: red;'>❌ <strong>PROBLEMA ENCONTRADO:</strong> La tabla 'reports' NO EXISTE</p>";
        echo "<p>🔧 <strong>SOLUCIÓN:</strong> Necesitas crear la tabla reports en tu base de datos.</p>";
        
        echo "<h3>📋 SQL para crear la tabla:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
        echo "CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voucher_id INT NOT NULL,
    company_id INT NOT NULL,
    payment_no INT NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    payment_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    capital_percentage DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    capital_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_payment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ytd_amount DECIMAL(12,2) DEFAULT 0.00,
    file_path VARCHAR(500) NULL,
    file_format ENUM('excel', 'pdf') DEFAULT 'excel',
    generation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generated_by INT NOT NULL,
    notes TEXT NULL,
    
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    INDEX idx_voucher_company (voucher_id, company_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_generation_date (generation_date)
);";
        echo "</pre>";
        exit;
    } else {
        echo "<p style='color: green;'>✅ Tabla 'reports' existe</p>";
    }
    
    // 2. Verificar estructura de la tabla
    echo "<h2>2. 📋 Estructura de la tabla 'reports'</h2>";
    $columns = $db->fetchAll("DESCRIBE reports");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Columna</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Verificar que hay registros en reports
    echo "<h2>3. 📊 Datos en la tabla 'reports'</h2>";
    $reports_count = $db->fetch("SELECT COUNT(*) as total FROM reports");
    echo "<p><strong>Total reportes:</strong> " . $reports_count['total'] . "</p>";
    
    if ($reports_count['total'] == 0) {
        echo "<p style='color: red;'>❌ <strong>PROBLEMA:</strong> No hay reportes generados</p>";
        echo "<p>🔧 <strong>SOLUCIÓN:</strong> Primero necesitas generar un reporte desde reports.php</p>";
    } else {
        // Mostrar algunos reportes
        $sample_reports = $db->fetchAll("SELECT * FROM reports ORDER BY id DESC LIMIT 5");
        echo "<h3>📋 Últimos reportes:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Voucher ID</th><th>Company ID</th><th>Payment No</th><th>File Path</th><th>Existe Archivo</th></tr>";
        
        foreach ($sample_reports as $report) {
            echo "<tr>";
            echo "<td>" . $report['id'] . "</td>";
            echo "<td>" . $report['voucher_id'] . "</td>";
            echo "<td>" . $report['company_id'] . "</td>";
            echo "<td>" . $report['payment_no'] . "</td>";
            echo "<td>" . ($report['file_path'] ?? 'NULL') . "</td>";
            
            if ($report['file_path'] && file_exists($report['file_path'])) {
                echo "<td style='color: green;'>✅ SÍ</td>";
            } else {
                echo "<td style='color: red;'>❌ NO</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Verificar el reporte específico solicitado
    if ($report_id) {
        echo "<h2>4. 🎯 Verificar reporte específico ID: {$report_id}</h2>";
        
        $specific_report = $db->fetch("SELECT * FROM reports WHERE id = ?", [$report_id]);
        
        if (!$specific_report) {
            echo "<p style='color: red;'>❌ <strong>PROBLEMA:</strong> El reporte con ID {$report_id} NO EXISTE</p>";
            echo "<p>🔧 <strong>SOLUCIÓN:</strong> Verifica que el ID es correcto o genera el reporte primero</p>";
        } else {
            echo "<p style='color: green;'>✅ Reporte encontrado</p>";
            echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
            print_r($specific_report);
            echo "</pre>";
            
            // Verificar archivo
            if ($specific_report['file_path']) {
                if (file_exists($specific_report['file_path'])) {
                    echo "<p style='color: green;'>✅ Archivo físico existe en: " . $specific_report['file_path'] . "</p>";
                } else {
                    echo "<p style='color: red;'>❌ Archivo físico NO EXISTE en: " . $specific_report['file_path'] . "</p>";
                    echo "<p>🔧 <strong>SOLUCIÓN:</strong> Regenerar el reporte</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ file_path es NULL</p>";
            }
        }
    }
    
    // 5. Verificar tablas relacionadas
    echo "<h2>5. 🔗 Verificar tablas relacionadas</h2>";
    
    // Verificar companies
    $companies_exist = $db->fetchAll("SHOW TABLES LIKE 'companies'");
    if (empty($companies_exist)) {
        echo "<p style='color: red;'>❌ Tabla 'companies' no existe</p>";
    } else {
        $companies_count = $db->fetch("SELECT COUNT(*) as total FROM companies");
        echo "<p style='color: green;'>✅ Tabla 'companies' existe (" . $companies_count['total'] . " registros)</p>";
    }
    
    // Verificar vouchers
    $vouchers_exist = $db->fetchAll("SHOW TABLES LIKE 'vouchers'");
    if (empty($vouchers_exist)) {
        echo "<p style='color: red;'>❌ Tabla 'vouchers' no existe</p>";
    } else {
        $vouchers_count = $db->fetch("SELECT COUNT(*) as total FROM vouchers");
        echo "<p style='color: green;'>✅ Tabla 'vouchers' existe (" . $vouchers_count['total'] . " registros)</p>";
    }
    
    // 6. Probar query completa
    if ($report_id && $specific_report) {
        echo "<h2>6. 🧪 Probar query completa del download-report.php</h2>";
        
        try {
            $full_query_result = $db->fetch("
                SELECT 
                    r.*,
                    c.name as company_name,
                    c.identifier as company_identifier,
                    v.voucher_number,
                    v.original_filename
                FROM reports r
                JOIN companies c ON r.company_id = c.id
                JOIN vouchers v ON r.voucher_id = v.id
                WHERE r.id = ?
            ", [$report_id]);
            
            if ($full_query_result) {
                echo "<p style='color: green;'>✅ Query completa funciona correctamente</p>";
                echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
                print_r($full_query_result);
                echo "</pre>";
            } else {
                echo "<p style='color: red;'>❌ Query completa falla - problema en JOINs</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error en query: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error general: " . $e->getMessage() . "</p>";
}

echo "<h2>🎯 SIGUIENTE PASO</h2>";
echo "<p>Basado en los resultados de arriba, te diré exactamente qué necesitas arreglar.</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3 { color: #333; }
pre { overflow-x: auto; }
table { width: 100%; }
th { background: #f0f0f0; }
</style>