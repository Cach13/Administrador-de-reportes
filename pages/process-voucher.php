<?php
/**
 * process-voucher.php
 * Página para procesar voucher y mostrar datos extraídos
 * Permite seleccionar empresa y generar reporte
 * Transport Management System
 * UBICACIÓN: pages/process-voucher.php
 */

// Incluir configuración y dependencias
require_once '../config/config.php';
require_once '../config/Database.php';
require_once '../config/AuthManager.php';
require_once '../classes/MartinMarietaProcessor.php';

// Inicializar componentes
$auth = new AuthManager();
$db = Database::getInstance();

// Verificar que usuario esté logueado
$auth->requireLogin();
$currentUser = $auth->getCurrentUser();

// Obtener voucher_id
$voucher_id = $_GET['id'] ?? null;
if (!$voucher_id) {
    header('Location: dashboard.php?error=voucher_not_specified');
    exit;
}

// Variables de estado
$processing_result = null;
$processing_error = null;
$voucher_info = null;
$companies_available = [];

try {
    // Obtener información del voucher
    $voucher_info = $db->selectOne(
        "SELECT * FROM vouchers WHERE id = ? AND uploaded_by = ?",
        [$voucher_id, $currentUser['id']]
    );
    
    if (!$voucher_info) {
        throw new Exception("Voucher no encontrado o sin permisos para acceder");
    }
    
    // Obtener todas las empresas disponibles
    $companies_available = $db->select(
        "SELECT id, name, identifier, capital_percentage FROM companies WHERE is_active = 1 ORDER BY name"
    );
    
    // Procesar si se envió el formulario de procesamiento completo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'process_complete') {
            // Procesamiento completo y guardado en BD
            $processor = new MartinMarietaProcessor($voucher_id);
            $processing_result = $processor->process();
            
        } elseif ($_POST['action'] === 'generate_report') {
            // Generar reporte para empresa seleccionada
            $selected_company_id = $_POST['company_id'] ?? null;
            if ($selected_company_id) {
                header("Location: generate-report.php?voucher_id={$voucher_id}&company_id={$selected_company_id}");
                exit;
            } else {
                $processing_error = "Por favor selecciona una empresa para generar el reporte";
            }
        }
    }
    
    // Si el voucher aún no está procesado, hacer preview
    if (!$processing_result && $voucher_info['status'] !== 'processed') {
        $processor = new MartinMarietaProcessor($voucher_id);
        $processing_result = $processor->preview(20); // Preview con más filas
    }
    
    // Si ya está procesado, obtener datos de la BD
    if ($voucher_info['status'] === 'processed') {
        $trips_data = $db->select(
            "SELECT t.*, c.name as company_name, c.identifier as company_identifier 
             FROM trips t 
             LEFT JOIN companies c ON t.company_id = c.id 
             WHERE t.voucher_id = ? 
             ORDER BY t.trip_date, t.id
             LIMIT 20",
            [$voucher_id]
        );
        
        $companies_found = $db->select(
            "SELECT c.identifier, c.name, COUNT(t.id) as trip_count, SUM(t.amount) as total_amount
             FROM trips t 
             JOIN companies c ON t.company_id = c.id 
             WHERE t.voucher_id = ?
             GROUP BY c.id, c.identifier, c.name
             ORDER BY trip_count DESC",
            [$voucher_id]
        );
        
        $processing_result = [
            'success' => true,
            'voucher_id' => $voucher_id,
            'stats' => [
                'total_rows_found' => $voucher_info['total_rows_found'],
                'valid_rows_extracted' => $voucher_info['valid_rows_extracted'],
                'rows_with_errors' => $voucher_info['rows_with_errors'],
                'extraction_confidence' => $voucher_info['extraction_confidence'],
                'companies_found' => array_column($companies_found, 'identifier')
            ],
            'data' => $trips_data,
            'companies_stats' => $companies_found,
            'is_processed' => true
        ];
    }
    
} catch (Exception $e) {
    $processing_error = $e->getMessage();
    logMessage('ERROR', "Error en process-voucher.php: " . $e->getMessage(), [
        'voucher_id' => $voucher_id,
        'user_id' => $currentUser['id']
    ]);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Voucher - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            line-height: 1.6;
        }
        
        /* HEADER */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* MAIN CONTAINER */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* VOUCHER INFO */
        .voucher-info {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
        }
        
        .info-item h4 {
            color: #4a5568;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .info-item p {
            color: #2d3748;
            font-weight: 600;
        }
        
        /* STATUS BADGES */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-uploaded { background: #faf089; color: #744210; }
        .status-processing { background: #bee3f8; color: #2c5282; }
        .status-processed { background: #c6f6d5; color: #22543d; }
        .status-error { background: #fed7d7; color: #822727; }
        
        /* STATS GRID */
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-number.success { color: #38a169; }
        .stat-number.warning { color: #d69e2e; }
        .stat-number.info { color: #3182ce; }
        .stat-number.danger { color: #e53e3e; }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
        }
        
        /* CONFIDENCE METER */
        .confidence-meter {
            margin-top: 1rem;
        }
        
        .confidence-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .confidence-fill {
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .confidence-high { background: #38a169; }
        .confidence-medium { background: #d69e2e; }
        .confidence-low { background: #e53e3e; }
        
        /* DATA TABLES */
        .data-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .section-header {
            background: #f7fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-weight: bold;
            color: #2d3748;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-content {
            padding: 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.9rem;
        }
        
        .data-table tr:hover {
            background: #f7fafc;
        }
        
        .data-table .amount {
            text-align: right;
            font-weight: 600;
            color: #2d3748;
        }
        
        .confidence-cell {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }
        
        .confidence-high-cell { background: #c6f6d5; color: #22543d; }
        .confidence-medium-cell { background: #faf089; color: #744210; }
        .confidence-low-cell { background: #fed7d7; color: #822727; }
        
        /* COMPANIES SECTION */
        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .company-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
            border-left: 4px solid #667eea;
        }
        
        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .company-name {
            font-weight: bold;
            color: #2d3748;
        }
        
        .company-identifier {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .company-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .company-stat {
            text-align: center;
        }
        
        .company-stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2d3748;
        }
        
        .company-stat-label {
            color: #718096;
            font-size: 0.8rem;
        }
        
        /* ACTIONS */
        .actions-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 1.5rem;
        }
        
        .action-card {
            padding: 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .action-card:hover {
            border-color: #667eea;
            background: #f7fafc;
        }
        
        .action-title {
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .action-description {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        /* FORM */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* ALERTS */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #c6f6d5;
        }
        
        .alert-error {
            background: #fff5f5;
            color: #822727;
            border: 1px solid #fed7d7;
        }
        
        .alert-warning {
            background: #fffaf0;
            color: #744210;
            border: 1px solid #faf089;
        }
        
        .alert-info {
            background: #ebf8ff;
            color: #2c5282;
            border: 1px solid #bee3f8;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .voucher-info {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .companies-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="dashboard.php" class="back-btn">
                    <span>←</span> Dashboard
                </a>
                <div class="page-title">Procesar Voucher</div>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 2)); ?>
                </div>
                <div>
                    <div style="font-weight: bold;"><?php echo $currentUser['full_name']; ?></div>
                    <div style="font-size: 0.9rem; opacity: 0.8;"><?php echo ucfirst($currentUser['role']); ?></div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- MAIN CONTAINER -->
    <div class="container">
        
        <?php if ($processing_error): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?php echo htmlspecialchars($processing_error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($voucher_info): ?>
        <!-- INFORMACIÓN DEL VOUCHER -->
        <div class="voucher-info">
            <div class="info-item">
                <h4>Archivo</h4>
                <p><?php echo htmlspecialchars($voucher_info['original_filename']); ?></p>
            </div>
            <div class="info-item">
                <h4>Voucher Number</h4>
                <p><?php echo htmlspecialchars($voucher_info['voucher_number']); ?></p>
            </div>
            <div class="info-item">
                <h4>Estado</h4>
                <p>
                    <span class="status-badge status-<?php echo $voucher_info['status']; ?>">
                        <?php echo ucfirst($voucher_info['status']); ?>
                    </span>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($processing_result && $processing_result['success']): ?>
        
        <!-- ESTADÍSTICAS DE EXTRACCIÓN -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number info"><?php echo $processing_result['stats']['total_rows_found']; ?></div>
                <div class="stat-label">Líneas Analizadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number success"><?php echo $processing_result['stats']['valid_rows_extracted']; ?></div>
                <div class="stat-label">Datos Extraídos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number warning"><?php echo $processing_result['stats']['rows_with_errors']; ?></div>
                <div class="stat-label">Errores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number info"><?php echo count($processing_result['stats']['companies_found']); ?></div>
                <div class="stat-label">Empresas Encontradas</div>
                <div class="confidence-meter">
                    <?php 
                    $confidence = $processing_result['stats']['extraction_confidence'] ?? 0;
                    $confidence_class = $confidence >= 0.8 ? 'confidence-high' : ($confidence >= 0.5 ? 'confidence-medium' : 'confidence-low');
                    ?>
                    <div class="confidence-bar">
                        <div class="confidence-fill <?php echo $confidence_class; ?>" 
                             style="width: <?php echo ($confidence * 100); ?>%"></div>
                    </div>
                    <small style="color: #718096; margin-top: 0.5rem; display: block;">
                        Confianza: <?php echo round($confidence * 100); ?>%
                    </small>
                </div>
            </div>
        </div>
        
        <!-- EMPRESAS ENCONTRADAS -->
        <?php if (!empty($processing_result['companies_stats'])): ?>
        <div class="data-section">
            <div class="section-header">
                <span>Empresas Identificadas</span>
                <span style="font-size: 0.9rem; font-weight: normal; color: #718096;">
                    <?php echo count($processing_result['companies_stats']); ?> empresas
                </span>
            </div>
            <div class="section-content">
                <div class="companies-grid" style="padding: 1.5rem;">
                    <?php foreach ($processing_result['companies_stats'] as $company): ?>
                    <div class="company-card">
                        <div class="company-header">
                            <div class="company-name"><?php echo htmlspecialchars($company['name']); ?></div>
                            <div class="company-identifier"><?php echo htmlspecialchars($company['identifier']); ?></div>
                        </div>
                        <div class="company-stats">
                            <div class="company-stat">
                                <div class="company-stat-number"><?php echo $company['trip_count']; ?></div>
                                <div class="company-stat-label">Viajes</div>
                            </div>
                            <div class="company-stat">
                                <div class="company-stat-number">$<?php echo number_format($company['total_amount'], 2); ?></div>
                                <div class="company-stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- PREVIEW DE DATOS -->
        <?php if (!empty($processing_result['data'])): ?>
        <div class="data-section">
            <div class="section-header">
                <span>Preview de Datos Extraídos</span>
                <span style="font-size: 0.9rem; font-weight: normal; color: #718096;">
                    Mostrando <?php echo count($processing_result['data']); ?> de <?php echo $processing_result['stats']['valid_rows_extracted']; ?> registros
                </span>
            </div>
            <div class="section-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Location</th>
                            <th>Ticket</th>
                            <th>Vehicle</th>
                            <th>Rate</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>Empresa</th>
                            <th>Confianza</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($processing_result['data'] as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['ship_date'] ?? $row['trip_date'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['location'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['ticket_number'] ?? ''); ?></td>
                            <td style="font-family: monospace;"><?php echo htmlspecialchars($row['vehicle_number'] ?? ''); ?></td>
                            <td class="amount">$<?php echo number_format($row['haul_rate'] ?? 0, 2); ?></td>
                            <td class="amount"><?php echo number_format($row['quantity'] ?? 0, 2); ?></td>
                            <td class="amount">$<?php echo number_format($row['amount'] ?? 0, 2); ?></td>
                            <td>
                                <?php if (isset($row['company_identifier'])): ?>
                                    <span class="company-identifier"><?php echo htmlspecialchars($row['company_identifier']); ?></span>
                                <?php else: ?>
                                    <span style="color: #a0aec0;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $conf = $row['confidence'] ?? $row['extraction_confidence'] ?? 0;
                                $conf_class = $conf >= 0.9 ? 'confidence-high-cell' : ($conf >= 0.7 ? 'confidence-medium-cell' : 'confidence-low-cell');
                                ?>
                                <span class="confidence-cell <?php echo $conf_class; ?>">
                                    <?php echo round($conf * 100); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ACCIONES -->
        <div class="actions-section">
            <h3 style="margin-bottom: 1rem; color: #2d3748;">¿Qué deseas hacer ahora?</h3>
            
            <div class="actions-grid">
                
                <!-- PROCESAR COMPLETAMENTE -->
                <?php if (!isset($processing_result['is_processed'])): ?>
                <div class="action-card">
                    <div class="action-title">Guardar Datos en Base de Datos</div>
                    <div class="action-description">
                        Procesar completamente el voucher y guardar todos los datos extraídos en la base de datos.
                        Esto permitirá generar reportes posteriormente.
                    </div>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="process_complete">
                        <button type="submit" class="btn btn-primary">
                            <span>💾</span> Procesar y Guardar
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- GENERAR REPORTE -->
                <div class="action-card">
                    <div class="action-title">Generar Reporte por Empresa</div>
                    <div class="action-description">
                        Selecciona una empresa específica para generar su reporte de pago en formato Capital Transport.
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_report">
                        <div class="form-group">
                            <label class="form-label">Seleccionar Empresa:</label>
                            <select name="company_id" class="form-control" required>
                                <option value="">-- Seleccionar empresa --</option>
                                <?php foreach ($companies_available as $company): ?>
                                    <?php if (in_array($company['identifier'], $processing_result['stats']['companies_found'])): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['name']); ?> (<?php echo $company['identifier']; ?>)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <span>📊</span> Generar Reporte
                        </button>
                    </form>
                </div>
                
            </div>
            
            <!-- INFORMACIÓN ADICIONAL -->
            <div class="alert alert-info" style="margin-top: 2rem;">
                <strong>Información:</strong> Una vez procesado completamente, los datos quedarán guardados en el sistema
                y podrás generar reportes múltiples para diferentes empresas sin necesidad de reprocesar el voucher.
            </div>
        </div>
        
        <?php else: ?>
        
        <!-- MENSAJE DE ERROR SI NO HAY DATOS -->
        <div class="alert alert-warning">
            <strong>Sin datos:</strong> No se pudieron extraer datos válidos del voucher. 
            Verifica que el archivo sea un PDF válido de Martin Marieta con el formato correcto.
        </div>
        
        <div class="actions-section">
            <div class="action-card" style="text-align: center;">
                <div class="action-title">¿Problemas con la extracción?</div>
                <div class="action-description">
                    Si el archivo contiene datos válidos pero no se están extrayendo correctamente,
                    puede ser que el formato sea ligeramente diferente al esperado.
                </div>
                <a href="upload-voucher.php" class="btn btn-secondary">
                    <span>📤</span> Subir Otro Voucher
                </a>
            </div>
        </div>
        
        <?php endif; ?>
        
    </div>
    
    <script>
        // Auto-actualizar confidence meters
        document.addEventListener('DOMContentLoaded', function() {
            const confidenceBars = document.querySelectorAll('.confidence-fill');
            confidenceBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
        
        // Confirmar procesamiento completo
        const processForm = document.querySelector('form[action=""] input[value="process_complete"]');
        if (processForm) {
            processForm.closest('form').addEventListener('submit', function(e) {
                if (!confirm('¿Estás seguro de que quieres procesar y guardar todos los datos? Esta acción no se puede deshacer.')) {
                    e.preventDefault();
                }
            });
        }
        
        // Validar selección de empresa para reporte
        const reportForm = document.querySelector('form input[value="generate_report"]');
        if (reportForm) {
            reportForm.closest('form').addEventListener('submit', function(e) {
                const companySelect = this.querySelector('select[name="company_id"]');
                if (!companySelect.value) {
                    e.preventDefault();
                    alert('Por favor selecciona una empresa para generar el reporte');
                    companySelect.focus();
                }
            });
        }
        
        // Mostrar loading en botones al enviar formularios
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span>⏳</span> Procesando...';
                    submitBtn.disabled = true;
                    
                    // Rehabilitar después de 10 segundos por si falla
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 10000);
                }
            });
        });
    </script>
</body>
</html>