<?php
/**
 * Ver Datos Extraídos - Con ruta corregida
 * Guardar como: view-data.php en la raíz del proyecto
 */

require_once 'includes/auth-check.php'; // Ruta corregida

// Obtener voucher_id
$voucher_id = $_GET['voucher_id'] ?? 5; // Por defecto voucher 5

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
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Extraídos - <?php echo htmlspecialchars($voucher['original_filename']); ?></title>
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .back-btn {
            background: #dc2626;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

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

        .trips-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #2c2c2c;
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

        .no-data h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header {
                padding: 1.5rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .summary-item {
                padding: 1rem;
            }

            .summary-number {
                font-size: 1.5rem;
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
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-database"></i> Datos Extraídos</h1>
            <p><i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($voucher['original_filename']); ?></p>
            <p>📊 Estado: <strong><?php echo ucfirst($voucher['status']); ?></strong> | 
               📅 Procesado: <?php echo $voucher['processed_at'] ? date('d/m/Y H:i', strtotime($voucher['processed_at'])) : 'N/A'; ?></p>
            <a href="pages/dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>

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
                    <div class="summary-number">$<?php echo number_format($stats['total_subtotal'], 2); ?></div>
                    <div class="summary-label">💰 Subtotal</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">$<?php echo number_format($stats['total_deductions'], 2); ?></div>
                    <div class="summary-label">📉 Deducciones</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number">$<?php echo number_format($stats['total_final'], 2); ?></div>
                    <div class="summary-label">💵 Total Final</div>
                </div>
                <div class="summary-item">
                    <div class="summary-number"><?php echo round($stats['avg_confidence'] * 100); ?>%</div>
                    <div class="summary-label">🎯 Confianza Promedio</div>
                </div>
            </div>
        </div>

        <!-- Trips Table -->
        <div class="trips-section">
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
                            <td><?php echo date('d/m/Y', strtotime($trip['trip_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($trip['company_name'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($trip['origin']); ?></td>
                            <td><?php echo htmlspecialchars($trip['destination']); ?></td>
                            <td><?php echo number_format($trip['weight_tons'], 2); ?></td>
                            <td>$<?php echo number_format($trip['unit_rate'], 2); ?></td>
                            <td class="amount">$<?php echo number_format($trip['subtotal'], 2); ?></td>
                            <td>$<?php echo number_format($trip['deduction_amount'], 2); ?></td>
                            <td class="amount"><strong>$<?php echo number_format($trip['total_amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($trip['vehicle_plate'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($trip['ticket_number'] ?? 'N/A'); ?></td>
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
        </div>

        <?php else: ?>
        <!-- No Data -->
        <div class="trips-section">
            <div class="no-data">
                <i class="fas fa-inbox"></i>
                <h3>No hay datos extraídos</h3>
                <p>Este archivo aún no ha sido procesado o no contiene datos válidos.</p>
                <p><strong>Estado actual:</strong> <?php echo ucfirst($voucher['status']); ?></p>
                <?php if ($voucher['status'] === 'error'): ?>
                <p style="color: #dc2626; margin-top: 1rem;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($voucher['processing_notes'] ?? 'Error desconocido'); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>