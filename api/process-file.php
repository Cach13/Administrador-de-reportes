<?php
/**
 * API Process File - VERSIÓN DEBUG LIMPIA
 * Ruta: /api/process-file.php
 */

// LIMPIAR CUALQUIER OUTPUT PREVIO
ob_clean();

// HEADERS JSON INMEDIATOS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// SUPRIMIR ERRORES DE PHP QUE ROMPEN EL JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['voucher_id'])) {
        throw new Exception('Datos inválidos - se requiere voucher_id');
    }
    
    $voucher_id = intval($input['voucher_id']);
    $companies = $input['companies'] ?? [];
    
    if ($voucher_id <= 0) {
        throw new Exception('ID de voucher inválido');
    }
    
    if (empty($companies)) {
        throw new Exception('Se requiere al menos una empresa');
    }
    
    // INCLUDES SIN MOSTRAR ERRORES
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    
    $db = Database::getInstance();
    
    // Verificar voucher
    $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
    
    if (!$voucher) {
        throw new Exception('Voucher no encontrado');
    }
    
    // Verificar estado
    if ($voucher['status'] === 'processed') {
        echo json_encode([
            'success' => true,
            'message' => 'Voucher ya procesado',
            'data' => [
                'voucher_id' => $voucher_id,
                'status' => 'processed'
            ]
        ]);
        exit;
    }
    
    if ($voucher['status'] === 'processing') {
        throw new Exception('Voucher ya en procesamiento');
    }
    
    // SIMULACIÓN DE PROCESAMIENTO EXITOSO (por ahora)
    // Cambiar estado a processing
    $db->update('vouchers', [
        'status' => 'processing'
    ], 'id = ?', [$voucher_id]);
    
    // Simular procesamiento con delay
    sleep(1);
    
    // CREAR TRIPS DE PRUEBA
    $trips_created = 0;
    $company_mapping = [
        'JAV' => 1, // Johnson & Associates LLC
        'MAR' => 2, // Martin Construction Company  
        'BRN' => 3, // Brown Transport Solutions
        'WIL' => 4  // Wilson Logistics Corp
    ];
    
    foreach ($companies as $company_code) {
        if (!isset($company_mapping[$company_code])) continue;
        
        $company_id = $company_mapping[$company_code];
        
        // Crear 2-3 trips de ejemplo para cada empresa
        for ($i = 1; $i <= rand(2, 3); $i++) {
            $trip_data = [
                'voucher_id' => $voucher_id,
                'company_id' => $company_id,
                'trip_date' => date('Y-m-d', strtotime("-" . rand(1, 30) . " days")),
                'location' => 'PLANT ' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'ticket_number' => 'T' . rand(100000, 999999),
                'haul_rate' => round(rand(15, 35) + (rand(0, 99) / 100), 2),
                'quantity' => round(rand(8, 25) + (rand(0, 99) / 100), 2),
                'amount' => 0, // Se calculará
                'vehicle_number' => 'RMT' . $company_code . str_pad($i, 3, '0', STR_PAD_LEFT),
                'source_row_number' => $trips_created + 1,
                'extraction_confidence' => round(rand(85, 99) / 100, 2)
            ];
            
            // Calcular amount = haul_rate * quantity
            $trip_data['amount'] = round($trip_data['haul_rate'] * $trip_data['quantity'], 2);
            
            $db->insert('trips', $trip_data);
            $trips_created++;
        }
    }
    
    // Actualizar voucher como procesado
    $db->update('vouchers', [
        'status' => 'processed',
        'total_rows_found' => $trips_created + rand(2, 5),
        'valid_rows_extracted' => $trips_created,
        'extraction_confidence' => 0.92
    ], 'id = ?', [$voucher_id]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Archivo procesado exitosamente',
        'data' => [
            'voucher_id' => $voucher_id,
            'trips_processed' => $trips_created,
            'companies_processed' => count($companies),
            'status' => 'processed'
        ]
    ]);
    
} catch (Exception $e) {
    // Log interno del error (sin mostrar)
    error_log("Process API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Asegurar que no hay output adicional
exit;
?>