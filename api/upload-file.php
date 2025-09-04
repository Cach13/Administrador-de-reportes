<?php
/**
 * Upload API - ESENCIAL para que funcione el dashboard
 */

header('Content-Type: application/json');
session_start();

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('No autenticado');
    }
    
    require_once '../config/config.php';
    require_once '../classes/Database.php';
    
    $db = Database::getInstance();
    
    if (!isset($_FILES['voucher_file'])) {
        throw new Exception('No se recibió archivo');
    }
    
    $file = $_FILES['voucher_file'];
    
    // Validaciones básicas
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error en upload');
    }
    
    if ($file['size'] > 20 * 1024 * 1024) {
        throw new Exception('Archivo muy grande (max 20MB)');
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        throw new Exception('Solo archivos PDF');
    }
    
    // Crear directorios
    $upload_dir = '../assets/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $pdf_dir = $upload_dir . 'pdf/';
    if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0755, true);
    
    // Nombre único
    $filename = date('Y-m-d_H-i-s') . '_' . uniqid() . '.pdf';
    $filepath = $pdf_dir . $filename;
    
    // Guardar archivo
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Error guardando archivo');
    }
    
    // Generar voucher number
    $voucher_number = 'VM_' . date('Ymd_His') . '_' . rand(1000, 9999);
    
    // Guardar en BD
    $voucher_id = $db->insert('vouchers', [
        'voucher_number' => $voucher_number,
        'original_filename' => $file['name'],
        'file_path' => $filepath,
        'file_size' => $file['size'],
        'file_hash' => md5_file($filepath),
        'file_type' => 'martin_marieta',
        'file_format' => 'pdf',
        'status' => 'uploaded',
        'uploaded_by' => $_SESSION['user_id'],
        'upload_date' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Archivo subido exitosamente',
        'voucher_id' => $voucher_id,
        'filename' => $file['name']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>