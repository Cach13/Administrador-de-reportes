<?php
/**
 * 🧹 PÁGINA DE LIMPIEZA: Eliminar vouchers con datos falsos
 * Ruta: /pages/cleanup-vouchers.php
 * 
 * Esta página te permite eliminar todos los vouchers que contienen
 * datos falsos como "PLANT 001", "T840437", etc.
 */

require_once '../includes/auth-check.php';
require_once '../classes/Database.php';

$db = Database::getInstance();

// Obtener todos los vouchers con trips que tienen datos falsos
$fake_vouchers = $db->fetchAll("
    SELECT DISTINCT
        v.id,
        v.voucher_number,
        v.original_filename,
        v.status,
        v.upload_date,
        COUNT(t.id) as trips_count,
        GROUP_CONCAT(DISTINCT t.location ORDER BY t.location SEPARATOR ', ') as locations_sample,
        GROUP_CONCAT(DISTINCT t.ticket_number ORDER BY t.ticket_number SEPARATOR ', ') as tickets_sample
    FROM vouchers v
    LEFT JOIN trips t ON v.id = t.voucher_id
    WHERE t.location LIKE 'PLANT %' 
       OR t.ticket_number LIKE 'T%'
       OR t.vehicle_number LIKE 'RMT%'
    GROUP BY v.id, v.voucher_number, v.original_filename, v.status, v.upload_date
    ORDER BY v.upload_date DESC
");

$page_title = "Limpieza de Vouchers con Datos Falsos";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Capital Transport</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-red: #dc2626;
            --dark-gray: #2c2c2c;
            --light-gray: #f5f5f5;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --white: #ffffff;
            --border-light: #e5e5e5;
            --text-muted: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--dark-gray) 0%, #1a1a1a 100%);
            color: var(--white);
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-logo {
            width: 40px;
            height: 40px;
            background: var(--primary-red);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .company-info h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-red);
        }

        .company-info p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-danger {
            background: #fee2e2;
            color: var(--error-red);
            border-left: 4px solid var(--error-red);
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid var(--warning-orange);
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-green);
        }

        .section {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            background: var(--primary-red);
            color: var(--white);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            padding: 2rem;
        }

        .voucher-card {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .voucher-card.fake-data {
            border-color: var(--error-red);
            background: #fef2f2;
        }

        .voucher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .voucher-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .voucher-details h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-gray);
        }

        .voucher-details p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .voucher-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: var(--error-red);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--warning-orange);
            color: var(--white);
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: var(--text-muted);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: #4b5563;
            color: var(--white);
            text-decoration: none;
        }

        .fake-data-sample {
            background: #fee2e2;
            border: 1px solid var(--error-red);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .fake-data-sample h6 {
            color: var(--error-red);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .fake-data-sample code {
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: var(--error-red);
        }

        .bulk-actions {
            padding: 1.5rem;
            background: #fef2f2;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .bulk-actions h3 {
            color: var(--error-red);
            margin-bottom: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--error-red);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--error-red);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .voucher-info {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Alert Container -->
    <div class="alert-container" id="alertContainer"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="header-logo">
                    <i class="fas fa-broom"></i>
                </div>
                <div class="company-info">
                    <h1>Limpieza de Datos Falsos</h1>
                    <p>Capital Transport LLP</p>
                </div>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem;"></i>
            <div>
                <h4 style="margin-bottom: 0.5rem;">¡Vouchers con Datos Falsos Detectados!</h4>
                <p style="margin: 0;">Se encontraron vouchers que contienen datos simulados como "PLANT 001", "T840437", etc. 
                Estos deben eliminarse antes de continuar con el procesamiento real.</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($fake_vouchers); ?></div>
                <div class="stat-label">Vouchers con Datos Falsos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_column($fake_vouchers, 'trips_count')); ?></div>
                <div class="stat-label">Trips Falsos Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="deletedCount">0</div>
                <div class="stat-label">Vouchers Eliminados</div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <?php if (!empty($fake_vouchers)): ?>
        <div class="bulk-actions">
            <h3><i class="fas fa-trash-alt"></i> Acción Masiva</h3>
            <p>Eliminar todos los vouchers con datos falsos de una vez</p>
            <button class="btn btn-danger" onclick="deleteAllFakeVouchers()" id="deleteAllBtn">
                <i class="fas fa-bomb"></i>
                Eliminar Todos los Vouchers Falsos (<?php echo count($fake_vouchers); ?>)
            </button>
        </div>

        <!-- Individual Vouchers -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-list"></i>
                    Vouchers con Datos Falsos Detectados
                </div>
                <div style="background: var(--white); color: var(--error-red); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                    <?php echo count($fake_vouchers); ?> vouchers
                </div>
            </div>
            <div class="section-content">
                <?php foreach ($fake_vouchers as $voucher): ?>
                <div class="voucher-card fake-data" id="voucher-<?php echo $voucher['id']; ?>">
                    <div class="voucher-info">
                        <div class="voucher-details">
                            <h4><?php echo htmlspecialchars($voucher['voucher_number']); ?></h4>
                            <p><strong>Archivo:</strong> <?php echo htmlspecialchars($voucher['original_filename']); ?></p>
                            <p><strong>Estado:</strong> <?php echo ucfirst($voucher['status']); ?></p>
                            <p><strong>Trips:</strong> <?php echo $voucher['trips_count']; ?> registros falsos</p>
                            <p><strong>Subido:</strong> <?php echo date('d/m/Y H:i', strtotime($voucher['upload_date'])); ?></p>
                        </div>
                        <div class="voucher-actions">
                            <button class="btn btn-danger" onclick="deleteVoucher(<?php echo $voucher['id']; ?>)" 
                                    id="deleteBtn-<?php echo $voucher['id']; ?>">
                                <i class="fas fa-trash"></i>
                                Eliminar
                            </button>
                        </div>
                    </div>
                    
                    <div class="fake-data-sample">
                        <h6><i class="fas fa-bug"></i> Muestra de Datos Falsos Detectados:</h6>
                        <p><strong>Locations:</strong> <code><?php echo htmlspecialchars(substr($voucher['locations_sample'], 0, 100)); ?>...</code></p>
                        <p><strong>Tickets:</strong> <code><?php echo htmlspecialchars(substr($voucher['tickets_sample'], 0, 100)); ?>...</code></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
            <div>
                <h4 style="margin-bottom: 0.5rem;">¡Sistema Limpio! ✨</h4>
                <p style="margin: 0;">No se encontraron vouchers con datos falsos. Tu sistema está listo para procesar archivos reales.</p>
            </div>
        </div>
        
        <div class="section">
            <div class="section-content" style="text-align: center; padding: 3rem;">
                <i class="fas fa-sparkles" style="font-size: 3rem; color: var(--success-green); margin-bottom: 1rem;"></i>
                <h3>¡Todo está limpio!</h3>
                <p style="margin-bottom: 2rem;">Puedes regresar al dashboard y comenzar a subir archivos PDF reales.</p>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i>
                    Ir al Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Variables globales
        let deletedCount = 0;
        const totalFakeVouchers = <?php echo count($fake_vouchers); ?>;

        // Función para mostrar alertas
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.style.cssText = 'padding: 1rem 1.5rem; margin-bottom: 1rem; border-radius: 12px; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); animation: slideIn 0.3s ease;';
            
            if (type === 'success') {
                alert.style.background = '#d1fae5';
                alert.style.color = '#065f46';
                alert.style.borderLeft = '4px solid #10b981';
            } else if (type === 'danger') {
                alert.style.background = '#fee2e2';
                alert.style.color = '#dc2626';
                alert.style.borderLeft = '4px solid #ef4444';
            }
            
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 1.2rem; margin-left: auto;">&times;</button>
            `;
            
            alertContainer.appendChild(alert);
            
            // Auto-remove después de 5 segundos
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 5000);
        }

        // Eliminar voucher individual
        async function deleteVoucher(voucherId) {
            if (!confirm('¿Estás seguro de eliminar este voucher y todos sus datos?')) {
                return;
            }

            const btn = document.getElementById(`deleteBtn-${voucherId}`);
            const voucherCard = document.getElementById(`voucher-${voucherId}`);
            
            // Mostrar loading
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
            btn.disabled = true;
            voucherCard.classList.add('loading');

            try {
                const response = await fetch('../api/delete-file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        voucher_id: voucherId,
                        force_delete: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Animación de eliminación
                    voucherCard.style.transform = 'scale(0)';
                    voucherCard.style.opacity = '0';
                    
                    setTimeout(() => {
                        voucherCard.remove();
                        deletedCount++;
                        document.getElementById('deletedCount').textContent = deletedCount;
                        
                        // Si se eliminaron todos, recargar página
                        if (deletedCount >= totalFakeVouchers) {
                            showAlert('success', '¡Todos los vouchers falsos han sido eliminados!');
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                    }, 300);
                    
                    showAlert('success', `Voucher eliminado: ${data.data.trips_deleted} trips eliminados`);
                } else {
                    throw new Error(data.message || 'Error eliminando voucher');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('danger', 'Error eliminando voucher: ' + error.message);
                
                // Restaurar botón
                btn.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
                btn.disabled = false;
                voucherCard.classList.remove('loading');
            }
        }

        // Eliminar todos los vouchers falsos
        async function deleteAllFakeVouchers() {
            if (!confirm(`¿Estás SEGURO de eliminar TODOS los ${totalFakeVouchers} vouchers con datos falsos?\n\nEsta acción NO se puede deshacer.`)) {
                return;
            }

            const deleteAllBtn = document.getElementById('deleteAllBtn');
            deleteAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando todos...';
            deleteAllBtn.disabled = true;

            const voucherIds = <?php echo json_encode(array_column($fake_vouchers, 'id')); ?>;
            let successCount = 0;
            let errorCount = 0;

            // Eliminar vouchers uno por uno para mejor feedback
            for (const voucherId of voucherIds) {
                try {
                    const response = await fetch('../api/delete-file.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            voucher_id: voucherId,
                            force_delete: true
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        successCount++;
                        // Ocultar voucher eliminado
                        const voucherCard = document.getElementById(`voucher-${voucherId}`);
                        if (voucherCard) {
                            voucherCard.style.opacity = '0.3';
                        }
                        
                        // Actualizar contador
                        document.getElementById('deletedCount').textContent = successCount;
                    } else {
                        errorCount++;
                    }
                } catch (error) {
                    errorCount++;
                    console.error(`Error eliminando voucher ${voucherId}:`, error);
                }

                // Pequeña pausa para no sobrecargar el servidor
                await new Promise(resolve => setTimeout(resolve, 200));
            }

            // Mostrar resultado final
            if (successCount === voucherIds.length) {
                showAlert('success', `¡Éxito! Se eliminaron todos los ${successCount} vouchers con datos falsos.`);
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showAlert('danger', `Eliminación parcial: ${successCount} exitosos, ${errorCount} errores.`);
                deleteAllBtn.innerHTML = '<i class="fas fa-bomb"></i> Reintentar Eliminación';
                deleteAllBtn.disabled = false;
            }
        }
    </script>
</body>
</html>