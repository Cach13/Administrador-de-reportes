<?php
/**
 * Ver Datos Extraídos - FUNCIONA CON TU BD REAL
 * Ruta: /pages/view-extracted-data.php
 */

require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Query usando SOLO las columnas que SÍ tienes en tu BD
$vouchers = $db->fetchAll("
    SELECT 
        v.id,
        v.voucher_number,
        v.original_filename,
        v.status,
        v.upload_date,
        v.total_rows_found,
        v.valid_rows_extracted,
        v.extraction_confidence,
        v.uploaded_by,
        COUNT(t.id) as trips_count,
        COUNT(DISTINCT t.company_id) as companies_count,
        COALESCE(SUM(t.amount), 0) as total_amount,
        u.full_name as uploaded_by_name
    FROM vouchers v
    LEFT JOIN trips t ON v.id = t.voucher_id
    LEFT JOIN users u ON v.uploaded_by = u.id
    GROUP BY v.id, v.voucher_number, v.original_filename, v.status,
             v.upload_date, v.total_rows_found, v.valid_rows_extracted, 
             v.extraction_confidence, v.uploaded_by, u.full_name
    ORDER BY v.upload_date DESC
");

// Si hay un voucher seleccionado, obtener sus trips
$selected_voucher = null;
$trips = [];
$companies_summary = [];

if (isset($_GET['voucher']) && is_numeric($_GET['voucher'])) {
    $voucher_id = intval($_GET['voucher']);
    
    // Información del voucher
    $selected_voucher = $db->fetch("
        SELECT v.*, u.full_name as uploaded_by_name
        FROM vouchers v
        LEFT JOIN users u ON v.uploaded_by = u.id
        WHERE v.id = ?
    ", [$voucher_id]);
    
    if ($selected_voucher) {
        // Trips extraídos
        $trips = $db->fetchAll("
            SELECT 
                t.*,
                c.name as company_name,
                c.identifier as company_identifier
            FROM trips t
            LEFT JOIN companies c ON t.company_id = c.id
            WHERE t.voucher_id = ?
            ORDER BY t.trip_date DESC, t.vehicle_number
        ", [$voucher_id]);
        
        // Resumen por empresa
        $companies_summary = $db->fetchAll("
            SELECT 
                c.id,
                c.name,
                c.identifier,
                COUNT(t.id) as trips_count,
                SUM(t.amount) as total_amount,
                MIN(t.trip_date) as first_trip,
                MAX(t.trip_date) as last_trip
            FROM trips t
            JOIN companies c ON t.company_id = c.id
            WHERE t.voucher_id = ?
            GROUP BY c.id, c.name, c.identifier
            ORDER BY trips_count DESC
        ", [$voucher_id]);
    }
}

$page_title = "Datos Extraídos";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Transport Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; }
        .main-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .voucher-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .confidence-bar {
            width: 100%;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }
        .confidence-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        .confidence-high { background: #28a745; }
        .confidence-medium { background: #ffc107; }
        .confidence-low { background: #dc3545; }
        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="main-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1"><i class="fas fa-database me-2"></i><?php echo $page_title; ?></h1>
                    <p class="mb-0 opacity-75">Visualizar información extraída de los archivos PDF</p>
                </div>
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($vouchers)): ?>
            <div class="no-data">
                <i class="fas fa-file-pdf"></i>
                <h3>No hay archivos procesados</h3>
                <p>Sube algunos archivos PDF desde el dashboard para ver los datos extraídos.</p>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Subir Archivo
                </a>
            </div>
        <?php else: ?>
            
            <!-- Lista de Vouchers -->
            <?php if (!$selected_voucher): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-list me-2"></i>Archivos en Sistema (<?php echo count($vouchers); ?>)</h2>
                            <div>
                                <span class="badge bg-success"><?php echo array_sum(array_column($vouchers, 'trips_count')); ?> viajes extraídos</span>
                                <span class="badge bg-info">$<?php echo number_format(array_sum(array_column($vouchers, 'total_amount')), 2); ?> total</span>
                            </div>
                        </div>
                        
                        <?php foreach ($vouchers as $voucher): ?>
                            <div class="voucher-card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <h5 class="mb-1">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                                <?php echo htmlspecialchars($voucher['voucher_number']); ?>
                                            </h5>
                                            <small class="text-muted"><?php echo htmlspecialchars($voucher['original_filename']); ?></small>
                                            <br>
                                            <small class="text-muted">
                                                Subido: <?php echo date('d/m/Y H:i', strtotime($voucher['upload_date'])); ?>
                                                por <?php echo htmlspecialchars($voucher['uploaded_by_name']); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <?php
                                            $status_classes = [
                                                'uploaded' => 'bg-secondary',
                                                'processing' => 'bg-warning',
                                                'processed' => 'bg-success',
                                                'error' => 'bg-danger'
                                            ];
                                            $status_class = $status_classes[$voucher['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> status-badge">
                                                <?php echo ucfirst($voucher['status']); ?>
                                            </span>
                                            <?php if ($voucher['extraction_confidence'] > 0): ?>
                                                <br><small class="text-success">
                                                    Conf: <?php echo round($voucher['extraction_confidence'] * 100, 1); ?>%
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="stat-number text-primary"><?php echo $voucher['trips_count']; ?></div>
                                            <small class="text-muted">Viajes</small>
                                            <?php if ($voucher['valid_rows_extracted'] > 0): ?>
                                                <br><small class="text-success">
                                                    <?php echo $voucher['valid_rows_extracted']; ?>/<?php echo $voucher['total_rows_found']; ?> extraídos
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="stat-number text-success"><?php echo $voucher['companies_count']; ?></div>
                                            <small class="text-muted">Empresas</small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="stat-number text-info">$<?php echo number_format($voucher['total_amount'], 2); ?></div>
                                            <small class="text-muted">Total</small>
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <?php if ($voucher['trips_count'] > 0): ?>
                                                <a href="?voucher=<?php echo $voucher['id']; ?>" class="btn btn-sm btn-primary" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php elseif ($voucher['status'] === 'uploaded'): ?>
                                                <button class="btn btn-sm btn-warning" onclick="processVoucher(<?php echo $voucher['id']; ?>)" title="Procesar archivo">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            
            <!-- Detalle del Voucher Seleccionado -->
            <?php else: ?>
                
                <!-- Header del Voucher -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2>
                                <i class="fas fa-file-alt me-2"></i>
                                <?php echo htmlspecialchars($selected_voucher['voucher_number']); ?>
                            </h2>
                            <a href="view-extracted-data.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Volver a la Lista
                            </a>
                        </div>
                        
                        <!-- Estadísticas del Voucher -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-number text-primary"><?php echo count($trips); ?></div>
                                <div class="text-muted">Viajes Extraídos</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number text-success"><?php echo count($companies_summary); ?></div>
                                <div class="text-muted">Empresas Encontradas</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number text-info">
                                    $<?php echo number_format(array_sum(array_column($companies_summary, 'total_amount')), 2); ?>
                                </div>
                                <div class="text-muted">Monto Total</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number text-warning">
                                    <?php echo round($selected_voucher['extraction_confidence'] * 100, 1); ?>%
                                </div>
                                <div class="text-muted">Confianza</div>
                            </div>
                        </div>
                        
                        <!-- Info del archivo -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5>Información del Archivo</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Archivo:</strong> <?php echo htmlspecialchars($selected_voucher['original_filename']); ?><br>
                                        <strong>Tipo:</strong> <?php echo strtoupper($selected_voucher['file_format']); ?><br>
                                        <strong>Estado:</strong> 
                                        <span class="badge bg-<?php echo str_replace('bg-', '', $status_classes[$selected_voucher['status']]); ?>">
                                            <?php echo ucfirst($selected_voucher['status']); ?>
                                        </span><br>
                                        <strong>Estadísticas:</strong> 
                                        <?php echo $selected_voucher['valid_rows_extracted']; ?>/<?php echo $selected_voucher['total_rows_found']; ?> filas válidas
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Subido:</strong> <?php echo date('d/m/Y H:i:s', strtotime($selected_voucher['upload_date'])); ?><br>
                                        <strong>Por:</strong> <?php echo htmlspecialchars($selected_voucher['uploaded_by_name']); ?><br>
                                        <strong>Confianza:</strong> <?php echo round($selected_voucher['extraction_confidence'] * 100, 1); ?>%<br>
                                        <strong>Tamaño:</strong> <?php echo number_format($selected_voucher['file_size'] / 1024, 1); ?> KB
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen por Empresas -->
                <?php if (!empty($companies_summary)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h4><i class="fas fa-building me-2"></i>Resumen por Empresa</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>Código</th>
                                        <th class="text-center">Viajes</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Período</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companies_summary as $company): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($company['name']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($company['identifier']); ?></span></td>
                                        <td class="text-center"><?php echo $company['trips_count']; ?></td>
                                        <td class="text-end">$<?php echo number_format($company['total_amount'], 2); ?></td>
                                        <td class="text-center">
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($company['first_trip'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($company['last_trip'])); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <a href="../pages/reports.php?voucher=<?php echo $selected_voucher['id']; ?>&company=<?php echo $company['id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-file-alt me-1"></i>Generar Reporte
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tabla de Trips -->
                <?php if (!empty($trips)): ?>
                <div class="row">
                    <div class="col-12">
                        <h4><i class="fas fa-truck me-2"></i>Detalle de Viajes (<?php echo count($trips); ?>)</h4>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Empresa</th>
                                        <th>Vehículo</th>
                                        <th>Ubicación</th>
                                        <th>Ticket</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end">Tarifa</th>
                                        <th class="text-end">Monto</th>
                                        <th class="text-center">Confianza</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trips as $trip): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($trip['trip_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($trip['company_identifier']); ?></span>
                                            <br><small><?php echo htmlspecialchars($trip['company_name']); ?></small>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($trip['vehicle_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($trip['location']); ?></td>
                                        <td><?php echo htmlspecialchars($trip['ticket_number'] ?? '-'); ?></td>
                                        <td class="text-end"><?php echo number_format($trip['quantity'], 2); ?></td>
                                        <td class="text-end">$<?php echo number_format($trip['haul_rate'], 2); ?></td>
                                        <td class="text-end"><strong>$<?php echo number_format($trip['amount'], 2); ?></strong></td>
                                        <td class="text-center">
                                            <div class="confidence-bar mb-1">
                                                <?php 
                                                $confidence = $trip['extraction_confidence'];
                                                $confidence_class = $confidence >= 0.8 ? 'confidence-high' : ($confidence >= 0.6 ? 'confidence-medium' : 'confidence-low');
                                                ?>
                                                <div class="confidence-fill <?php echo $confidence_class; ?>" 
                                                     style="width: <?php echo ($confidence * 100); ?>%;"></div>
                                            </div>
                                            <small><?php echo round($confidence * 100); ?>%</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>No hay datos extraídos</h3>
                    <p>Este archivo aún no ha sido procesado correctamente.</p>
                    <p><strong>Estado:</strong> <?php echo ucfirst($selected_voucher['status']); ?></p>
                    <?php if ($selected_voucher['status'] === 'uploaded'): ?>
                        <button class="btn btn-warning mt-3" onclick="processVoucher(<?php echo $selected_voucher['id']; ?>)">
                            <i class="fas fa-cog me-2"></i>Procesar Archivo
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function processVoucher(voucherId) {
        if (confirm('¿Procesar este voucher para extraer los datos?')) {
            const btn = event.target.closest('button');
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;
            
            fetch('../api/process-file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    voucher_id: voucherId,
                    companies: ['JAV', 'MAR', 'BRN', 'WIL'] // Todas las empresas disponibles
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Voucher procesado exitosamente: ' + data.data.trips_processed + ' viajes extraídos');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Error de conexión: ' + error.message);
                btn.innerHTML = originalContent;
                btn.disabled = false;
            });
        }
    }
    </script>
</body>
</html>