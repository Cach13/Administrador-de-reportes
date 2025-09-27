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
        $processing_result = $processor->preview(20);
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
        
        /* VOUCHER INFO CARD */
        .voucher-info-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .voucher-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .voucher-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2d3748;
        }
        
        .voucher-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-processed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .voucher-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: #718096;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 600;
            color: #2d3748;
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
        
        /* STATISTICS GRID */
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
        
        /* ACTION BUTTONS */
        .action-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .action-section h3 {
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(72, 187, 120, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        /* FORM STYLES */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d2d6dc;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* DATA TABLE */
        .data-preview {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .data-preview h3 {
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table th {
            background: #f7fafc;
            font-weight: 600;
            color: #2d3748;
        }
        
        .data-table tr:hover {
            background: #f7fafc;
        }
        
        /* COMPANIES STATS */
        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .company-card {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .company-name {
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .company-stats {
            font-size: 0.9rem;
            color: #718096;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .voucher-details {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .companies-grid {
                grid-template-columns: 1fr;
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
        
        <!-- VOUCHER INFO CARD -->
        <?php if ($voucher_info): ?>
        <div class="voucher-info-card">
            <div class="voucher-info-header">
                <h2 class="voucher-title"><?php echo htmlspecialchars($voucher_info['original_filename']); ?></h2>
                <span class="voucher-status status-<?php echo $voucher_info['status']; ?>">
                    <?php echo ucfirst($voucher_info['status']); ?>
                </span>
            </div>
            
            <div class="voucher-details">
                <div class="detail-item">
                    <div class="detail-label">Fecha de Subida</div>
                    <div class="detail-value"><?php echo isset($voucher_info['created_at']) && $voucher_info['created_at'] ? date('d/m/Y H:i', strtotime($voucher_info['created_at'])) : 'No disponible'; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tamaño del Archivo</div>
                    <div class="detail-value"><?php echo isset($voucher_info['file_size']) && $voucher_info['file_size'] ? number_format($voucher_info['file_size'] / 1024, 2) . ' KB' : 'No disponible'; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tipo de Archivo</div>
                    <div class="detail-value"><?php echo strtoupper(pathinfo($voucher_info['original_filename'], PATHINFO_EXTENSION)); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">ID del Voucher</div>
                    <div class="detail-value">#<?php echo $voucher_info['id']; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ALERT MESSAGES -->
        <?php if ($processing_error): ?>
        <div class="alert alert-error">
            <strong>Error:</strong> <?php echo htmlspecialchars($processing_error); ?>
        </div>
        <?php endif; ?>
        
        <!-- PROCESSING RESULTS -->
        <?php if ($processing_result && $processing_result['success']): ?>
        
        <!-- ESTADÍSTICAS DE EXTRACCIÓN -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number info"><?php echo $processing_result['stats']['total_rows_found'] ?? 0; ?></div>
                <div class="stat-label">Total de Líneas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number success"><?php echo $processing_result['stats']['valid_rows_extracted'] ?? 0; ?></div>
                <div class="stat-label">Registros Válidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number warning"><?php echo $processing_result['stats']['rows_with_errors'] ?? 0; ?></div>
                <div class="stat-label">Con Errores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number info"><?php echo count($processing_result['stats']['companies_found'] ?? []); ?></div>
                <div class="stat-label">Empresas Detectadas</div>
            </div>
        </div>
        
        <!-- PREVIEW DE DATOS -->
        <?php if (!empty($processing_result['data'])): ?>
        <div class="data-preview">
            <h3>Preview de Datos Extraídos</h3>
            <p>Mostrando los primeros <?php echo count($processing_result['data']); ?> registros:</p>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ubicación</th>
                        <th>Ticket</th>
                        <th>Vehículo</th>
                        <th>Cantidad</th>
                        <th>Monto</th>
                        <th>Empresa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($processing_result['data'], 0, 10) as $row): ?>
                    <tr>
                        <td><?php echo isset($row['trip_date']) ? date('d/m/Y', strtotime($row['trip_date'])) : ($row['date'] ?? 'N/A'); ?></td>
                        <td><?php echo $row['location'] ?? 'N/A'; ?></td>
                        <td><?php echo $row['ticket_number'] ?? 'N/A'; ?></td>
                        <td><?php echo $row['vehicle_number'] ?? 'N/A'; ?></td>
                        <td><?php echo isset($row['quantity']) ? number_format($row['quantity'], 2) : 'N/A'; ?></td>
                        <td>$<?php echo isset($row['amount']) ? number_format($row['amount'], 2) : 'N/A'; ?></td>
                        <td><?php echo $row['company_name'] ?? $row['company'] ?? 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- ESTADÍSTICAS POR EMPRESA -->
        <?php if (!empty($processing_result['companies_stats'])): ?>
        <div class="data-preview">
            <h3>Resumen por Empresa</h3>
            <div class="companies-grid">
                <?php foreach ($processing_result['companies_stats'] as $company): ?>
                <div class="company-card">
                    <div class="company-name"><?php echo htmlspecialchars($company['name'] ?? $company['identifier']); ?></div>
                    <div class="company-stats">
                        <?php echo $company['trip_count'] ?? 0; ?> viajes • 
                        $<?php echo number_format($company['total_amount'] ?? 0, 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ACCIONES DISPONIBLES -->
        <div class="action-section">
            <h3>Acciones Disponibles</h3>
            
            <?php if ($voucher_info['status'] !== 'processed'): ?>
            <!-- PROCESAR VOUCHER COMPLETO -->
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="process_complete">
                <button type="submit" class="btn btn-primary">
                    🔄 Procesar y Guardar Datos
                </button>
            </form>
            <?php endif; ?>
            
            <?php if ($voucher_info['status'] === 'processed' && !empty($companies_available)): ?>
            <!-- GENERAR REPORTE POR EMPRESA -->
            <form method="post" style="display: inline-block; margin-left: 1rem;">
                <input type="hidden" name="action" value="generate_report">
                <div class="form-group" style="margin-bottom: 1rem; width: 300px; display: inline-block; vertical-align: top;">
                    <label class="form-label">Seleccionar Empresa:</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">-- Seleccionar Empresa --</option>
                        <?php foreach ($companies_available as $company): ?>
                        <option value="<?php echo $company['id']; ?>">
                            <?php echo htmlspecialchars($company['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="vertical-align: bottom; margin-left: 1rem;">
                    📊 Generar Reporte
                </button>
            </form>
            <?php endif; ?>
            
            <!-- VOLVER AL DASHBOARD -->
            <a href="dashboard.php" class="btn btn-outline">
                ← Volver al Dashboard
            </a>
        </div>
        
        <?php else: ?>
        
        <!-- MENSAJE CUANDO NO HAY DATOS -->
        <div class="alert alert-info">
            <strong>Información:</strong> El voucher aún no ha sido procesado o no se pudieron extraer datos válidos.
        </div>
        
        <!-- ACCIONES INICIALES -->
        <div class="action-section">
            <h3>Procesar Voucher</h3>
            <p>Haz clic en el botón para analizar y extraer los datos del voucher:</p>
            
            <form method="post">
                <input type="hidden" name="action" value="process_complete">
                <button type="submit" class="btn btn-primary">
                    🔍 Analizar Voucher
                </button>
            </form>
        </div>
        
        <?php endif; ?>
        
    </div>
    
</body>
</html>