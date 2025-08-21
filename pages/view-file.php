<?php
/**
 * Vista de detalles completos de un archivo - VERSIÓN MEJORADA
 * Ruta: /pages/view-file.php
 */

require_once '../includes/auth-check.php';

// Obtener ID del voucher
$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id) {
    header('Location: dashboard.php');
    exit();
}

try {
    $db = Database::getInstance();
    
    // Obtener información del voucher
    $voucher = $db->fetch("
        SELECT v.*, u.full_name as uploaded_by_name
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        WHERE v.id = ?
    ", [$voucher_id]);
    
    if (!$voucher) {
        throw new Exception("Voucher {$voucher_id} no encontrado");
    }
    
    // Obtener trips
    $trips = $db->fetchAll("
        SELECT 
            t.*,
            c.name as company_name
        FROM trips t
        LEFT JOIN companies c ON t.company_id = c.id
        WHERE t.voucher_id = ?
        ORDER BY t.trip_date, t.id
    ", [$voucher_id]);
    
    // Estadísticas
    if (!empty($trips)) {
        $stats = [
            'total_trips' => count($trips),
            'total_weight' => array_sum(array_column($trips, 'weight_tons')),
            'total_subtotal' => array_sum(array_column($trips, 'subtotal')),
            'total_deductions' => array_sum(array_column($trips, 'deduction_amount')),
            'total_final' => array_sum(array_column($trips, 'total_amount')),
            'avg_confidence' => array_sum(array_column($trips, 'extraction_confidence')) / count($trips)
        ];
    } else {
        $stats = [
            'total_trips' => 0,
            'total_weight' => 0,
            'total_subtotal' => 0,
            'total_deductions' => 0,
            'total_final' => 0,
            'avg_confidence' => 0
        ];
    }
    
    // Obtener logs de procesamiento
    $processing_logs = $db->fetchAll("
        SELECT * FROM file_processing_logs 
        WHERE voucher_id = ? 
        ORDER BY created_at ASC
    ", [$voucher_id]);
    
    // Obtener errores de validación
    $validation_errors = $db->fetchAll("
        SELECT * FROM data_validation_errors 
        WHERE voucher_id = ? 
        ORDER BY row_number ASC
        LIMIT 20
    ", [$voucher_id]);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Archivo - <?php echo e($voucher['original_filename']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #2c2c2c;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .back-btn {
            background: #dc2626;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .file-overview {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .file-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .file-icon {
            width: 60px;
            height: 60px;
            background: #dc2626;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .file-info h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .file-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .meta-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .meta-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .meta-value {
            font-size: 1.1rem;
            color: #2c2c2c;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-processed { background: #d1fae5; color: #065f46; }
        .status-processing { background: #fef3c7; color: #92400e; }
        .status-uploaded { background: #e0e7ff; color: #3730a3; }
        .status-error { background: #fecaca; color: #dc2626; }

        .summary-box {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.3);
        }

        .summary-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .summary-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .summary-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: block;
        }

        .summary-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }

        .trips-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .trips-table th {
            background: #f8f9fa;
            color: #2c2c2c;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        .trips-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .trips-table tr:hover {
            background: #f8f9fa;
        }

        .amount {
            font-weight: 600;
            color: #059669;
        }

        .confidence-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .confidence-bar {
            width: 60px;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }

        .confidence-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .confidence-high { background: #10b981; }
        .confidence-medium { background: #f59e0b; }
        .confidence-low { background: #ef4444; }

        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: #dc2626;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .log-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .log-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .log-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .log-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #dc2626;
        }

        .error-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .error-table th,
        .error-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .error-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .file-header {
                flex-direction: column;
                text-align: center;
            }

            .file-meta {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .actions {
                flex-direction: column;
            }

            .trips-table {
                font-size: 0.8rem;
            }

            .trips-table th,
            .trips-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <h1>Detalles del Archivo</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- File Overview -->
        <section class="file-overview">
            <div class="file-header">
                <div class="file-icon">
                    <i class="fas fa-file-<?php echo $voucher['file_type'] === 'pdf' ? 'pdf' : 'excel'; ?>"></i>
                </div>
                <div class="file-info">
                    <h1><?php echo e($voucher['original_filename']); ?></h1>
                    <span class="status-badge status-<?php echo $voucher['status']; ?>">
                        <?php echo ucfirst($voucher['status']); ?>
                    </span>
                </div>
            </div>

            <div class="file-meta">
                <div class="meta-item">
                    <div class="meta-label">Número de Voucher</div>
                    <div class="meta-value"><?php echo e($voucher['voucher_number']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Tipo de Archivo</div>
                    <div class="meta-value"><?php echo strtoupper($voucher['file_type']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Tamaño</div>
                    <div class="meta-value"><?php echo formatFileSize($voucher['file_size']); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Fecha de Subida</div>
                    <div class="meta-value"><?php echo formatDate($voucher['upload_date'], 'd/m/Y H:i'); ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Subido por</div>
                    <div class="meta-value"><?php echo e($voucher['uploaded_by_name']); ?></div>
                </div>
                <?php if ($voucher['processed_at']): ?>
                <div class="meta-item">
                    <div class="meta-label">Procesado</div>
                    <div class="meta-value"><?php echo formatDate($voucher['processed_at'], 'd/m/Y H:i'); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($stats['total_trips'] > 0): ?>
        <!-- Summary -->
        <div class="summary-box">
            <div class="summary-title">📊 Resumen de Datos Extraídos</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-number"><?php echo number_format($stats['total_trips']); ?></div>
                    <div class="summary-label">🚛 Viajes Procesados</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo number_format($stats['total_weight'], 1); ?></div>
                    <div class="summary-label">⚖️ Toneladas Totales</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo formatCurrency($stats['total_subtotal']); ?></div>
                    <div class="summary-label">💰 Subtotal</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo formatCurrency($stats['total_deductions']); ?></div>
                    <div class="summary-label">📉 Deducciones</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo formatCurrency($stats['total_final']); ?></div>
                    <div class="summary-label">💵 Total Final</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo round($stats['avg_confidence'] * 100); ?>%</div>
                    <div class="summary-label">🎯 Confianza Promedio</div>
                </div>
            </div>
        </div>

        <!-- Trips Table -->
        <section class="section">
            <h2 class="section-title">
                <i class="fas fa-truck"></i>
                Detalle de Viajes (<?php echo count($trips); ?>)
            </h2>
            
            <div class="table-container">
                <table class="trips-table">
                    <thead>
                        <tr>
                            <th>📅 Fecha</th>
                            <th>🏢 Empresa</th>
                            <th>📍 Origen</th>
                            <th>🎯 Destino</th>
                            <th>⚖️ Toneladas</th>
                            <th>💲 Tarifa</th>
                            <th>💰 Subtotal</th>
                            <th>📉 Deducción</th>
                            <th>💵 Total</th>
                            <th>🚛 Vehículo</th>
                            <th>🎫 Ticket</th>
                            <th>🎯 Confianza</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td><?php echo formatDate($trip['trip_date']); ?></td>
                            <td><strong><?php echo e($trip['company_name'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo e($trip['origin']); ?></td>
                            <td><?php echo e($trip['destination']); ?></td>
                            <td><?php echo number_format($trip['weight_tons'], 2); ?></td>
                            <td><?php echo formatCurrency($trip['unit_rate']); ?></td>
                            <td class="amount"><?php echo formatCurrency($trip['subtotal']); ?></td>
                            <td><?php echo formatCurrency($trip['deduction_amount']); ?></td>
                            <td class="amount"><strong><?php echo formatCurrency($trip['total_amount']); ?></strong></td>
                            <td><?php echo e($trip['vehicle_plate'] ?? 'N/A'); ?></td>
                            <td><?php echo e($trip['ticket_number'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="confidence-indicator">
                                    <div class="confidence-bar">
                                        <div class="confidence-fill <?php echo $trip['extraction_confidence'] >= 0.8 ? 'confidence-high' : ($trip['extraction_confidence'] >= 0.6 ? 'confidence-medium' : 'confidence-low'); ?>" 
                                             style="width: <?php echo ($trip['extraction_confidence'] * 100); ?>%;"></div>
                                    </div>
                                    <span><?php echo round($trip['extraction_confidence'] * 100); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php else: ?>
        <!-- No Data -->
        <section class="section">
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <h3>No hay datos extraídos</h3>
                <p>Este archivo aún no ha sido procesado o no contiene datos válidos.</p>
                <p><strong>Estado actual:</strong> <?php echo ucfirst($voucher['status']); ?></p>
                <?php if ($voucher['status'] === 'error'): ?>
                <p style="color: #dc2626; margin-top: 1rem;">
                    <strong>Error:</strong> <?php echo e($voucher['processing_notes'] ?? 'Error desconocido'); ?>
                </p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Processing Logs (solo mostrar si existen) -->
        <?php if (!empty($processing_logs)): ?>
        <section class="section">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Historial de Procesamiento
            </h2>
            
            <div class="log-timeline">
                <?php foreach ($processing_logs as $log): ?>
                <div class="log-item">
                    <div><strong><?php echo e($log['processing_step']); ?></strong> - <?php echo e($log['step_status']); ?></div>
                    <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                        <?php echo formatDate($log['created_at'], 'd/m/Y H:i:s'); ?>
                        <?php if ($log['step_details']): ?>
                        - <?php echo e(json_decode($log['step_details'], true)['details'] ?? ''); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Validation Errors (solo si existen) -->
        <?php if (!empty($validation_errors)): ?>
        <section class="section">
            <h2 class="section-title">
                <i class="fas fa-exclamation-triangle"></i>
                Errores de Validación (<?php echo count($validation_errors); ?>)
            </h2>
            
            <table class="error-table">
                <thead>
                    <tr>
                        <th>Fila</th>
                        <th>Campo</th>
                        <th>Error</th>
                        <th>Valor Original</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($validation_errors as $error): ?>
                    <tr>
                        <td><?php echo $error['row_number']; ?></td>
                        <td><?php echo e($error['field_name']); ?></td>
                        <td><?php echo e($error['error_message']); ?></td>
                        <td><?php echo e($error['original_value']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <!-- Actions -->
        <div class="actions">
            <?php if ($voucher['status'] === 'uploaded' || $voucher['status'] === 'error'): ?>
            <button class="action-btn btn-primary" onclick="processFile(<?php echo $voucher['id']; ?>)">
                <i class="fas fa-play"></i>
                Procesar Archivo
            </button>
            <?php endif; ?>
            
            <?php if ($voucher['status'] === 'processed' && $stats['total_trips'] > 0): ?>
            <a href="../download-reports.php?voucher_id=<?php echo $voucher['id']; ?>" class="action-btn btn-success">
                <i class="fas fa-download"></i>
                Descargar Excel
            </a>
            <button class="action-btn btn-warning" onclick="reprocessFile(<?php echo $voucher['id']; ?>)">
                <i class="fas fa-redo"></i>
                Re-procesar
            </button>
            <?php endif; ?>
            
            <?php if (hasPermission('manage_users')): ?>
            <button class="action-btn btn-secondary" onclick="deleteFile(<?php echo $voucher['id']; ?>)">
                <i class="fas fa-trash"></i>
                Eliminar Archivo
            </button>
            <?php endif; ?>
        </div>
    </main>

    <script>
        async function processFile(voucherId) {
            if (!confirm('¿Procesar este archivo?')) return;
            
            try {
                const response = await fetch('../api/process-file.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ voucher_id: voucherId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Archivo procesado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error procesando archivo: ' + error.message);
            }
        }

        async function reprocessFile(voucherId) {
            if (!confirm('¿Re-procesar este archivo? Esto mantendrá los datos existentes como respaldo.')) return;
            
            try {
                alert('Re-procesamiento iniciado...');
                location.reload(); // Por simplicidad, recargar para mostrar el cambio
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function deleteFile(voucherId) {
            if (!confirm('¿Eliminar este archivo permanentemente? Esta acción no se puede deshacer.')) return;
            
            try {
                const response = await fetch('../api/delete-file.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ voucher_id: voucherId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Archivo eliminado correctamente');
                    window.location.href = 'dashboard.php';
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error eliminando archivo: ' + error.message);
            }
        }
    </script>
</body>
</html>