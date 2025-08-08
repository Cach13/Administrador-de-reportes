<?php
/**
 * Capital Transport LLP Report Manager
 * Dashboard Principal - Focused on Voucher Management
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Datos del usuario
$user = [
    'name' => $_SESSION['username'] ?? 'Admin',
    'role' => $_SESSION['role'] ?? 'admin',
    'last_login' => date('Y-m-d H:i:s')
];

// Datos de vouchers de ejemplo (en producción vendrá de la base de datos)
$vouchers = [
    [
        'id' => 1,
        'filename' => 'voucher_2024_001.pdf',
        'upload_date' => '2024-08-07 14:30:00',
        'status' => 'processed',
        'companies_count' => 8,
        'total_amount' => 45250.00,
        'processed_by' => 'Admin'
    ],
    [
        'id' => 2,
        'filename' => 'voucher_2024_002.pdf',
        'upload_date' => '2024-08-07 16:45:00',
        'status' => 'processing',
        'companies_count' => 5,
        'total_amount' => 28750.00,
        'processed_by' => 'Admin'
    ],
    [
        'id' => 3,
        'filename' => 'voucher_2024_003.pdf',
        'upload_date' => '2024-08-08 09:15:00',
        'status' => 'pending',
        'companies_count' => 0,
        'total_amount' => 0.00,
        'processed_by' => '-'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Capital Transport LLP</title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #2c2c2c;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
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
            background: #dc2626;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo i {
            color: white;
            font-size: 1.2rem;
        }

        .company-info h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #dc2626;
        }

        .company-info p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 1rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
            text-transform: capitalize;
        }

        .logout-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Upload Section */
        .upload-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border-left: 4px solid #dc2626;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c2c2c;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-area {
            border: 2px dashed #dc2626;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background: #fef2f2;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            background: #fee2e2;
            border-color: #b91c1c;
        }

        .upload-area.dragover {
            background: #dc2626;
            color: white;
        }

        .upload-icon {
            font-size: 3rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .upload-area.dragover .upload-icon {
            color: white;
        }

        .upload-text {
            font-size: 1.2rem;
            font-weight: 500;
            color: #2c2c2c;
            margin-bottom: 0.5rem;
        }

        .upload-area.dragover .upload-text {
            color: white;
        }

        .upload-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .upload-area.dragover .upload-subtitle {
            color: rgba(255, 255, 255, 0.8);
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        /* Vouchers Table Section */
        .vouchers-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        .vouchers-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .vouchers-table th {
            background: #f8f9fa;
            color: #2c2c2c;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        .vouchers-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .vouchers-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-processed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-processing {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pending {
            background: #fee2e2;
            color: #991b1b;
        }

        .amount {
            font-weight: 600;
            color: #059669;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-view {
            background: #0284c7;
            color: white;
        }

        .btn-view:hover {
            background: #0369a1;
        }

        .btn-process {
            background: #dc2626;
            color: white;
        }

        .btn-process:hover {
            background: #b91c1c;
        }

        .btn-download {
            background: #059669;
            color: white;
        }

        .btn-download:hover {
            background: #047857;
        }

        .btn-delete {
            background: #dc2626;
            color: white;
        }

        .btn-delete:hover {
            background: #b91c1c;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Progress Bar */
        .progress-container {
            display: none;
            margin-top: 1rem;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #dc2626, #ef4444);
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .main-content {
                padding: 1rem;
            }

            .upload-section, .vouchers-section {
                padding: 1.5rem;
            }

            .upload-area {
                padding: 2rem 1rem;
            }

            .upload-icon {
                font-size: 2rem;
            }

            .vouchers-table {
                font-size: 0.8rem;
            }

            .vouchers-table th,
            .vouchers-table td {
                padding: 0.75rem 0.5rem;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="header-logo">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="company-info">
                    <h1>Capital Transport LLP</h1>
                    <p>Report Manager System</p>
                </div>
            </div>
            
            <div class="user-section">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Upload Section -->
        <section class="upload-section">
            <h2 class="section-title">
                <i class="fas fa-upload"></i>
                Upload New Voucher
            </h2>
            
            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="upload-text">Drop your PDF voucher here</div>
                <div class="upload-subtitle">or click to browse files</div>
                <button type="button" class="upload-btn" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-folder-open"></i>
                    Choose File
                </button>
            </div>
            
            <input type="file" id="fileInput" class="file-input" accept=".pdf" multiple>
            
            <div class="progress-container" id="progressContainer">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text" id="progressText">Uploading...</div>
            </div>
        </section>

        <!-- Vouchers Table -->
        <section class="vouchers-section">
            <h2 class="section-title">
                <i class="fas fa-table"></i>
                Uploaded Vouchers
            </h2>
            
            <?php if (empty($vouchers)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No vouchers uploaded yet</p>
                    <p>Upload your first PDF voucher to get started</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="vouchers-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Upload Date</th>
                                <th>Status</th>
                                <th>Companies</th>
                                <th>Total Amount</th>
                                <th>Processed By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vouchers as $voucher): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-pdf text-danger me-2"></i>
                                        <?php echo htmlspecialchars($voucher['filename']); ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($voucher['upload_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $voucher['status']; ?>">
                                            <?php echo ucfirst($voucher['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $voucher['companies_count']; ?></td>
                                    <td class="amount">$<?php echo number_format($voucher['total_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($voucher['processed_by']); ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="action-btn btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($voucher['status'] === 'pending'): ?>
                                                <button class="action-btn btn-process" title="Process Voucher">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($voucher['status'] === 'processed'): ?>
                                                <button class="action-btn btn-download" title="Download Reports">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="action-btn btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Upload functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const progressContainer = document.getElementById('progressContainer');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFiles(files);
            }
        });

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFiles(e.target.files);
            }
        });

        function handleFiles(files) {
            const file = files[0];
            
            if (file.type !== 'application/pdf') {
                alert('Please select a PDF file only.');
                return;
            }

            // Show progress
            progressContainer.style.display = 'block';
            progressText.textContent = `Uploading ${file.name}...`;
            
            // Simulate upload progress
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 100) progress = 100;
                
                progressFill.style.width = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(interval);
                    progressText.textContent = 'Upload completed! Processing...';
                    
                    setTimeout(() => {
                        progressContainer.style.display = 'none';
                        progressFill.style.width = '0%';
                        alert('Voucher uploaded successfully!');
                        // In real implementation, this would refresh the table
                        location.reload();
                    }, 1000);
                }
            }, 200);
        }

        // Action buttons functionality
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-view')) {
                alert('View voucher details - Feature coming soon!');
            } else if (e.target.closest('.btn-process')) {
                if (confirm('Process this voucher and extract company data?')) {
                    alert('Processing voucher - Feature coming soon!');
                }
            } else if (e.target.closest('.btn-download')) {
                alert('Download reports - Feature coming soon!');
            } else if (e.target.closest('.btn-delete')) {
                if (confirm('Are you sure you want to delete this voucher?')) {
                    alert('Delete voucher - Feature coming soon!');
                }
            }
        });

        // Entry animation
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.upload-section, .vouchers-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    section.style.transition = 'all 0.6s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>