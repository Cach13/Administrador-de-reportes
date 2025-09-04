<?php
/**
 * Process Page - Procesamiento Step-by-Step
 * Integra MartinMarietaProcessor con interfaz visual
 */

require_once '../includes/auth-check.php';
require_once '../classes/Database.php';
require_once '../classes/MartinMarietaProcessor.php';

$db = Database::getInstance();

// Obtener voucher ID
$voucher_id = $_GET['voucher_id'] ?? null;
$step = $_GET['step'] ?? 'select';

if (!$voucher_id) {
    header('Location: dashboard.php');
    exit();
}

// Obtener información del voucher
try {
    $voucher = $db->fetch("SELECT * FROM vouchers WHERE id = ?", [$voucher_id]);
    if (!$voucher) {
        throw new Exception("Voucher no encontrado");
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: dashboard.php');
    exit();
}

// Obtener empresas disponibles
$companies = $db->fetchAll("SELECT * FROM companies WHERE active = 1 ORDER BY name");

// Variables para el procesamiento
$processing_result = null;
$selected_companies = [];
$step_error = '';

// Manejar POST para procesamiento
if ($_POST && $step === 'process') {
    $selected_companies = $_POST['companies'] ?? [];
    
    if (empty($selected_companies)) {
        $step_error = 'Debes seleccionar al menos una empresa para procesar.';
    } else {
        try {
            $processor = new MartinMarietaProcessor();
            $processing_result = $processor->processVoucher($voucher_id, $selected_companies);
            
            if ($processing_result['success']) {
                // Actualizar estado del voucher
                $db->update('vouchers', ['status' => 'processed', 'processed_at' => date('Y-m-d H:i:s')], 'id = ?', [$voucher_id]);
                $step = 'completed';
            } else {
                $step_error = $processing_result['message'] ?? 'Error en el procesamiento';
            }
        } catch (Exception $e) {
            $step_error = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = "Procesar Voucher";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Transport Management</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-red: #dc2626;
            --dark-gray: #2c2c2c;
            --light-gray: #f5f5f5;
            --white: #ffffff;
            --text-muted: #6b7280;
            --border-light: #e5e7eb;
            --success-green: #059669;
            --warning-yellow: #d97706;
        }

        body {
            background: var(--light-gray);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .process-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* HEADER */
        .process-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        .breadcrumb-nav a {
            color: var(--primary-red);
            text-decoration: none;
        }

        .voucher-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .voucher-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-red);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .voucher-details h2 {
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .voucher-meta {
            color: var(--text-muted);
        }

        /* STEP INDICATOR */
        .step-indicator {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
        }

        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .step.pending .step-circle {
            background: var(--border-light);
            color: var(--text-muted);
        }

        .step.active .step-circle {
            background: var(--primary-red);
            color: white;
        }

        .step.completed .step-circle {
            background: var(--success-green);
            color: white;
        }

        .step-label {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .step-line {
            position: absolute;
            top: 25px;
            left: 60px;
            width: calc(100% + 1rem);
            height: 2px;
            background: var(--border-light);
        }

        .step.completed .step-line {
            background: var(--success-green);
        }

        /* CONTENT CARDS */
        .content-card {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .company-card {
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .company-card:hover {
            border-color: var(--primary-red);
            transform: translateY(-2px);
        }

        .company-card.selected {
            border-color: var(--primary-red);
            background: #fef2f2;
        }

        .company-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .company-checkbox {
            transform: scale(1.3);
            accent-color: var(--primary-red);
        }

        .company-name {
            font-weight: 700;
            color: var(--dark-gray);
        }

        .company-identifier {
            background: var(--primary-red);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .company-percentage {
            color: var(--success-green);
            font-weight: 600;
        }

        /* PROCESSING RESULT */
        .processing-result {
            margin-top: 2rem;
        }

        .result-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .result-stat {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }

        .result-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: 0.5rem;
        }

        .result-label {
            color: var(--text-muted);
            font-weight: 600;
        }

        /* BUTTONS */
        .btn-primary-custom {
            background: var(--primary-red);
            border-color: var(--primary-red);
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            background: #b91c1c;
            border-color: #b91c1c;
            transform: translateY(-2px);
        }

        .btn-outline-custom {
            border: 2px solid var(--primary-red);
            color: var(--primary-red);
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background: var(--primary-red);
            color: white;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .process-container {
                padding: 1rem;
            }
            
            .steps {
                flex-direction: column;
                gap: 1rem;
            }
            
            .step-line {
                display: none;
            }
            
            .company-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php require_once '../includes/navbar.php'; ?>

    <div class="process-container">
        <!-- Header -->
        <div class="process-header">
            <div class="breadcrumb-nav">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Procesar Voucher</span>
            </div>
            
            <div class="voucher-info">
                <div class="voucher-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="voucher-details">
                    <h2><?php echo htmlspecialchars($voucher['original_filename']); ?></h2>
                    <div class="voucher-meta">
                        <i class="fas fa-calendar"></i> Subido: <?php echo date('d/m/Y H:i', strtotime($voucher['created_at'])); ?> • 
                        <i class="fas fa-info-circle"></i> Estado: <strong><?php echo ucfirst($voucher['status']); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="steps">
                <div class="step <?php echo $step === 'select' ? 'active' : ($step === 'process' || $step === 'completed' ? 'completed' : 'pending'); ?>">
                    <div class="step-circle">
                        <?php if ($step === 'select'): ?>
                            1
                        <?php else: ?>
                            <i class="fas fa-check"></i>
                        <?php endif; ?>
                    </div>
                    <div class="step-label">Seleccionar Empresas</div>
                    <div class="step-line"></div>
                </div>
                
                <div class="step <?php echo $step === 'process' ? 'active' : ($step === 'completed' ? 'completed' : 'pending'); ?>">
                    <div class="step-circle">
                        <?php if ($step === 'process'): ?>
                            2
                        <?php elseif ($step === 'completed'): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            2
                        <?php endif; ?>
                    </div>
                    <div class="step-label">Procesar Datos</div>
                    <div class="step-line"></div>
                </div>
                
                <div class="step <?php echo $step === 'completed' ? 'active' : 'pending'; ?>">
                    <div class="step-circle">
                        <?php if ($step === 'completed'): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            3
                        <?php endif; ?>
                    </div>
                    <div class="step-label">Completado</div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content-card">
            <?php if ($step === 'select'): ?>
                <!-- STEP 1: Select Companies -->
                <h3><i class="fas fa-building"></i> Seleccionar Empresas para Procesar</h3>
                <p class="text-muted mb-4">Elige las empresas cuyos datos quieres extraer de este voucher.</p>
                
                <?php if (!empty($step_error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $step_error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="?voucher_id=<?php echo $voucher_id; ?>&step=process">
                    <div class="company-grid">
                        <?php foreach ($companies as $company): ?>
                        <div class="company-card" data-company-id="<?php echo $company['id']; ?>">
                            <div class="company-header">
                                <input type="checkbox" 
                                       name="companies[]" 
                                       value="<?php echo $company['id']; ?>"
                                       class="company-checkbox"
                                       id="company_<?php echo $company['id']; ?>">
                                <label for="company_<?php echo $company['id']; ?>" class="mb-0 flex-grow-1">
                                    <div class="company-name"><?php echo htmlspecialchars($company['name']); ?></div>
                                </label>
                                <span class="company-identifier"><?php echo htmlspecialchars($company['identifier']); ?></span>
                            </div>
                            
                            <div class="company-details">
                                <div><strong>Razón Social:</strong> <?php echo htmlspecialchars($company['legal_name']); ?></div>
                                <div><strong>Capital %:</strong> <span class="company-percentage"><?php echo number_format($company['capital_percentage'], 2); ?>%</span></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-outline-custom me-3">
                            <i class="fas fa-arrow-left"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="fas fa-cogs"></i> Procesar Voucher
                        </button>
                    </div>
                </form>

            <?php elseif ($step === 'process'): ?>
                <!-- STEP 2: Processing -->
                <div class="text-center">
                    <div class="spinner-border text-danger mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h3>Procesando Voucher...</h3>
                    <p class="text-muted">Extrayendo datos y creando viajes. Por favor espera.</p>
                </div>
                
                <script>
                    // Auto-refresh para simular procesamiento
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                </script>

            <?php elseif ($step === 'completed'): ?>
                <!-- STEP 3: Completed -->
                <div class="text-center mb-4">
                    <div class="text-success mb-3">
                        <i class="fas fa-check-circle" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="text-success">¡Procesamiento Completado!</h3>
                    <p class="text-muted">El voucher ha sido procesado exitosamente.</p>
                </div>
                
                <?php if ($processing_result && $processing_result['success']): ?>
                <div class="processing-result">
                    <h4><i class="fas fa-chart-bar"></i> Resumen del Procesamiento</h4>
                    
                    <div class="result-summary">
                        <div class="result-stat">
                            <div class="result-number"><?php echo $processing_result['stats']['trips_created'] ?? 0; ?></div>
                            <div class="result-label">Viajes Creados</div>
                        </div>
                        <div class="result-stat">
                            <div class="result-number"><?php echo count($selected_companies ?? []); ?></div>
                            <div class="result-label">Empresas Procesadas</div>
                        </div>
                        <div class="result-stat">
                            <div class="result-number">$<?php echo number_format($processing_result['stats']['total_amount'] ?? 0, 2); ?></div>
                            <div class="result-label">Monto Total</div>
                        </div>
                        <div class="result-stat">
                            <div class="result-number"><?php echo number_format($processing_result['stats']['avg_confidence'] ?? 0, 1); ?>%</div>
                            <div class="result-label">Confianza Promedio</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($processing_result['warnings'])): ?>
                    <div class="alert alert-warning mt-3">
                        <h6><i class="fas fa-exclamation-triangle"></i> Advertencias:</h6>
                        <ul class="mb-0">
                            <?php foreach ($processing_result['warnings'] as $warning): ?>
                            <li><?php echo htmlspecialchars($warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="view-data.php?id=<?php echo $voucher_id; ?>" class="btn btn-outline-custom me-3">
                        <i class="fas fa-eye"></i> Ver Datos Extraídos
                    </a>
                    <a href="reports.php" class="btn btn-primary-custom me-3">
                        <i class="fas fa-chart-line"></i> Generar Reporte
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Volver al Dashboard
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Company card selection
            const companyCards = document.querySelectorAll('.company-card');
            const checkboxes = document.querySelectorAll('.company-checkbox');
            
            companyCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (e.target.type === 'checkbox') return;
                    
                    const checkbox = card.querySelector('.company-checkbox');
                    checkbox.checked = !checkbox.checked;
                    updateCardSelection(card, checkbox.checked);
                });
            });
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const card = this.closest('.company-card');
                    updateCardSelection(card, this.checked);
                });
            });
            
            function updateCardSelection(card, selected) {
                if (selected) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            }
            
            // Initialize card states
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.company-card');
                updateCardSelection(card, checkbox.checked);
            });
        });
    </script>
</body>
</html>