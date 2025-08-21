<?php
/**
 * Test del PDFExtractor v2.0 - Verificación Martin Marietta
 * Colocar en la raíz del proyecto y ejecutar desde navegador
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Test PDFExtractor v2.0 - Martin Marietta</h1>";

try {
    // Incluir dependencias
    require_once 'config/config.php';
    require_once 'classes/Database.php';
    require_once 'classes/PDFExtractor.php';
    
    echo "<h2>1. ✅ Librerías cargadas correctamente</h2>";
    
    // Buscar el PDF de Martin Marietta en la BD
    $db = Database::getInstance();
    $voucher = $db->fetch("
        SELECT * FROM vouchers 
        WHERE original_filename LIKE '%martin%' 
           OR original_filename LIKE '%marietta%' 
           OR original_filename LIKE '%haul%'
        ORDER BY id DESC 
        LIMIT 1
    ");
    
    if (!$voucher) {
        echo "<p>❌ No se encontró PDF de Martin Marietta en BD</p>";
        exit();
    }
    
    echo "<h2>2. ✅ PDF encontrado: {$voucher['original_filename']}</h2>";
    echo "<p>📁 ID: {$voucher['id']}</p>";
    echo "<p>📁 Ruta: {$voucher['file_path']}</p>";
    echo "<p>📊 Estado: {$voucher['status']}</p>";
    
    // Verificar archivo físico
    if (!file_exists($voucher['file_path'])) {
        echo "<p>❌ Archivo físico no existe: {$voucher['file_path']}</p>";
        exit();
    }
    
    echo "<h2>3. ✅ Archivo físico existe</h2>";
    echo "<p>📏 Tamaño: " . round(filesize($voucher['file_path']) / 1024, 2) . " KB</p>";
    
    // Crear extractor
    $extractor = new PDFExtractor($voucher['id']);
    
    echo "<h2>4. ✅ PDFExtractor creado</h2>";
    
    // Obtener información del PDF
    $pdf_info = $extractor->getPDFInfo();
    echo "<h3>📋 Información del PDF:</h3>";
    echo "<ul>";
    foreach ($pdf_info as $key => $value) {
        echo "<li><strong>{$key}:</strong> {$value}</li>";
    }
    echo "</ul>";
    
    // Preview del contenido
    echo "<h2>5. 🔍 Preview del contenido</h2>";
    $preview = $extractor->previewContent(500);
    
    echo "<h3>📊 Análisis inicial:</h3>";
    echo "<ul>";
    echo "<li><strong>Es Martin Marietta:</strong> " . ($preview['is_martin_marietta'] ? '✅ SÍ' : '❌ NO') . "</li>";
    echo "<li><strong>Longitud total del texto:</strong> {$preview['total_text_length']} caracteres</li>";
    echo "<li><strong>Filas estimadas:</strong> {$preview['estimated_rows']}</li>";
    echo "</ul>";
    
    if (!empty($preview['sample_data_lines'])) {
        echo "<h3>📋 Líneas de datos encontradas:</h3>";
        echo "<ol>";
        foreach ($preview['sample_data_lines'] as $line) {
            echo "<li><code>" . htmlspecialchars($line) . "</code></li>";
        }
        echo "</ol>";
    }
    
    // Obtener estadísticas de Martin Marietta
    echo "<h2>6. 📊 Estadísticas de Martin Marietta</h2>";
    $stats = $extractor->getMarinMariettaStats();
    
    echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3>📈 Resumen de datos:</h3>";
    echo "<ul>";
    echo "<li><strong>Filas totales encontradas:</strong> {$stats['total_rows_found']}</li>";
    echo "<li><strong>Filas positivas (válidas):</strong> {$stats['positive_rows']}</li>";
    echo "<li><strong>Filas negativas (correcciones):</strong> {$stats['negative_rows']}</li>";
    echo "<li><strong>Peso total:</strong> " . number_format($stats['total_weight_tons'], 2) . " toneladas</li>";
    echo "<li><strong>Monto total:</strong> $" . number_format($stats['total_amount_dollars'], 2) . "</li>";
    echo "<li><strong>Tarifa promedio:</strong> $" . number_format($stats['average_rate'], 2) . " por tonelada</li>";
    echo "</ul>";
    echo "</div>";
    
    // Debug: Mostrar líneas que coinciden
    echo "<h2>7. 🔬 Debug: Líneas que coinciden con el patrón</h2>";
    $matching_lines = $extractor->debugGetMatchingLines(5);
    
    if (!empty($matching_lines)) {
        echo "<h3>✅ Líneas encontradas:</h3>";
        foreach ($matching_lines as $i => $line_data) {
            echo "<div style='background: #f8f9fa; border-left: 4px solid #28a745; padding: 10px; margin: 5px 0;'>";
            echo "<strong>Línea {$line_data['line_number']}:</strong><br>";
            echo "<code>" . htmlspecialchars($line_data['content']) . "</code><br>";
            echo "<small>Grupos capturados: " . count($line_data['matches']) . "</small>";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 5px 0;'>";
        echo "⚠️ No se encontraron líneas que coincidan con el patrón actualizado";
        echo "</div>";
    }
    
    // Test de procesamiento completo
    echo "<h2>8. 🚀 Test de procesamiento completo</h2>";
    
    try {
        $start_time = microtime(true);
        $result = $extractor->process();
        $processing_time = microtime(true) - $start_time;
        
        echo "<div style='background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0;'>";
        echo "<h3>🎉 ¡Procesamiento exitoso!</h3>";
        echo "<ul>";
        echo "<li><strong>Viajes procesados:</strong> {$result['trips_processed']}</li>";
        echo "<li><strong>Puntuación de calidad:</strong> " . round($result['quality_score'] * 100) . "%</li>";
        echo "<li><strong>Tiempo de procesamiento:</strong> " . round($processing_time, 2) . " segundos</li>";
        echo "</ul>";
        echo "</div>";
        
        // Verificar datos en BD
        $trips = $db->fetchAll("
            SELECT t.*, c.name as company_name 
            FROM trips t 
            JOIN companies c ON t.company_id = c.id
            WHERE t.voucher_id = ? 
            ORDER BY t.trip_date 
            LIMIT 5
        ", [$voucher['id']]);
        
        if (!empty($trips)) {
            echo "<h3>📋 Primeros 5 viajes guardados en BD:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
            echo "<tr style='background: #f8f9fa;'>";
            echo "<th>Fecha</th><th>Empresa</th><th>Origen</th><th>Destino</th><th>Peso</th><th>Tarifa</th><th>Total</th>";
            echo "</tr>";
            
            foreach ($trips as $trip) {
                echo "<tr>";
                echo "<td>{$trip['trip_date']}</td>";
                echo "<td>{$trip['company_name']}</td>";
                echo "<td>" . substr($trip['origin'], 0, 20) . "</td>";
                echo "<td>" . substr($trip['destination'], 0, 20) . "</td>";
                echo "<td>{$trip['weight_tons']}</td>";
                echo "<td>\${$trip['unit_rate']}</td>";
                echo "<td>\$" . number_format($trip['total_amount'], 2) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0;'>";
        echo "<h3>❌ Error en procesamiento:</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>🎯 Resumen Final:</h2>";
    
    if ($stats['positive_rows'] > 0) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px;'>";
        echo "<h3>✅ ÉXITO - PDFExtractor funcionando correctamente</h3>";
        echo "<p>✅ Se encontraron y parsearon {$stats['positive_rows']} líneas de datos válidas</p>";
        echo "<p>✅ Total de {$stats['total_weight_tons']} toneladas por \$" . number_format($stats['total_amount_dollars'], 2) . "</p>";
        echo "<p>✅ El patrón regex está funcionando correctamente</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px;'>";
        echo "<h3>❌ PROBLEMA - Necesita más ajustes</h3>";
        echo "<p>❌ No se pudieron extraer datos válidos</p>";
        echo "<p>🔧 Revisa los patrones regex en PDFExtractor.php</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0;'>";
    echo "<h2>❌ Error general:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Test completado - " . date('Y-m-d H:i:s') . "</small></p>";
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
code { 
    background-color: #f4f4f4; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Courier New', monospace;
    font-size: 12px;
}
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; font-weight: bold; }
</style>