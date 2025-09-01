<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capital Transport Report Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* PALETA DE COLORES ORIGINAL */
        :root {
            --primary-red: #dc2626;
            --dark-gray: #2c2c2c;
            --darker-gray: #1a1a1a;
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

        /* HEADER */
        .header {
            background: linear-gradient(135deg, var(--dark-gray) 0%, var(--darker-gray) 100%);
            color: var(--white);
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
            background: var(--primary-red);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .header-logo:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
        }

        .header-logo i {
            color: var(--white);
            font-size: 1.2rem;
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

        .user-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            background: var(--primary-red);
            color: var(--white);
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
            color: var(--white);
            text-decoration: none;
        }

        /* MAIN CONTENT */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* STEP INDICATOR */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background: var(--white);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            right: -50%;
            width: 100%;
            height: 3px;
            background-color: var(--border-light);
            z-index: -1;
        }

        .step:last-child::after {
            display: none;
        }

        .step.active .step-circle {
            background: var(--primary-red);
            color: var(--white);
            animation: pulse 2s infinite;
        }

        .step.completed .step-circle {
            background: var(--success-green);
            color: var(--white);
        }

        .step.completed::after {
            background: var(--success-green);
        }

        .step-circle {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--border-light);
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }

        .step-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-gray);
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }

        /* UPLOAD SECTION */
        .section {
            background: var(--white);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-red);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-red);
            font-size: 1.3rem;
        }

        .upload-zone {
            border: 3px dashed var(--primary-red);
            border-radius: 16px;
            padding: 3rem 2rem;
            text-align: center;
            background: #fef2f2;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-zone:hover {
            background: #fee2e2;
            border-color: #b91c1c;
            transform: scale(1.02);
        }

        .upload-zone.dragover {
            background: var(--primary-red);
            color: var(--white);
            transform: scale(1.05);
        }

        .upload-icon {
            font-size: 4rem;
            color: var(--primary-red);
            margin-bottom: 1rem;
        }

        .upload-zone.dragover .upload-icon {
            color: var(--white);
        }

        .upload-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .upload-zone.dragover .upload-title {
            color: var(--white);
        }

        .upload-subtitle {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .upload-zone.dragover .upload-subtitle {
            color: rgba(255, 255, 255, 0.9);
        }

        .file-types {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .file-type {
            background: rgba(220, 38, 38, 0.1);
            border: 2px solid rgba(220, 38, 38, 0.2);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            min-width: 120px;
        }

        .upload-zone.dragover .file-type {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .file-type i {
            font-size: 2rem;
            color: var(--primary-red);
            margin-bottom: 0.5rem;
        }

        .upload-zone.dragover .file-type i {
            color: var(--white);
        }

        .file-type-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--primary-red);
        }

        .upload-zone.dragover .file-type-label {
            color: var(--white);
        }

        /* BUTTONS */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-red);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #b91c1c;
            transform: translateY(-1px);
            color: var(--white);
            text-decoration: none;
        }

        .btn-success {
            background: var(--success-green);
            color: var(--white);
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            color: var(--white);
            text-decoration: none;
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

        /* PROGRESS */
        .progress-container {
            margin: 1rem 0;
            display: none;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-red), #f87171);
            width: 0%;
            transition: width 0.3s ease;
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--light-gray);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            border-left: 4px solid var(--primary-red);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        /* DATA TABLE */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th {
            background: var(--dark-gray);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }

        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        /* COMPANY CARDS */
        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        .company-card {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .company-card:hover {
            border-color: var(--primary-red);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .company-card.selected {
            border-color: var(--primary-red);
            background: #fef2f2;
        }

        .company-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .company-checkbox {
            transform: scale(1.2);
            accent-color: var(--primary-red);
        }

        .company-name {
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .company-identifier {
            background: var(--primary-red);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        /* ALERTS */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-green);
        }

        .alert-error {
            background: #fee2e2;
            color: var(--error-red);
            border-left: 4px solid var(--error-red);
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid var(--warning-orange);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* UTILITIES */
        .d-none { display: none !important; }
        .d-flex { display: flex !important; }
        .text-center { text-align: center; }
        .mb-2 { margin-bottom: 1rem; }
        .mt-2 { margin-top: 1rem; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .main-content {
                padding: 1rem;
            }

            .step-indicator {
                flex-direction: column;
                gap: 1rem;
            }

            .step::after {
                display: none;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .company-grid {
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
                    <i class="fas fa-truck"></i>
                </div>
                <div class="company-info">
                    <h1>Capital Transport LLP</h1>
                    <p>Report Management System</p>
                </div>
            </div>
            <div class="user-section">
                <div>
                    <div style="font-weight: 600;">Administrator</div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">Admin User</div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="step-circle">1</div>
                <div class="step-title">Upload Voucher</div>
            </div>
            <div class="step" id="step2">
                <div class="step-circle">2</div>
                <div class="step-title">Preview Data</div>
            </div>
            <div class="step" id="step3">
                <div class="step-circle">3</div>
                <div class="step-title">Select Companies</div>
            </div>
            <div class="step" id="step4">
                <div class="step-circle">4</div>
                <div class="step-title">Process</div>
            </div>
            <div class="step" id="step5">
                <div class="step-circle">5</div>
                <div class="step-title">Generate Report</div>
            </div>
        </div>

        <!-- Step 1: Upload -->
        <div id="uploadStep" class="section">
            <h2 class="section-title">
                <i class="fas fa-upload"></i>
                Upload Martin Marieta Voucher
            </h2>
            
            <div class="upload-zone" id="uploadZone">
                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                <h3 class="upload-title">Drop your Martin Marieta file here</h3>
                <p class="upload-subtitle">Or click to browse and select a file</p>
                
                <div class="file-types">
                    <div class="file-type">
                        <i class="fas fa-file-pdf"></i>
                        <div class="file-type-label">PDF Files</div>
                    </div>
                    <div class="file-type">
                        <i class="fas fa-file-excel"></i>
                        <div class="file-type-label">Excel Files</div>
                    </div>
                </div>
                
                <input type="file" id="fileInput" class="d-none" accept=".pdf,.xlsx,.xls">
            </div>
            
            <div class="progress-container" id="uploadProgress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <p class="text-center mt-2">Uploading file...</p>
            </div>
        </div>

        <!-- Step 2: Preview -->
        <div id="previewStep" class="section d-none">
            <div class="d-flex" style="justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 class="section-title mb-0">
                    <i class="fas fa-eye"></i>
                    Data Preview
                </h2>
                <button class="btn btn-primary" onclick="showCompanySelection()">
                    Continue <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="totalRows">0</div>
                    <div class="stat-label">Total Rows</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="validRows">0</div>
                    <div class="stat-label">Valid Rows</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="companiesFound">0</div>
                    <div class="stat-label">Companies Found</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">95%</div>
                    <div class="stat-label">Confidence</div>
                </div>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ship Date</th>
                        <th>Location</th>
                        <th>Ticket Number</th>
                        <th>Haul Rate</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                        <th>Vehicle Number</th>
                        <th>Company</th>
                    </tr>
                </thead>
                <tbody id="previewTableBody">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <!-- Step 3: Company Selection -->
        <div id="companyStep" class="section d-none">
            <div class="d-flex" style="justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 class="section-title mb-0">
                    <i class="fas fa-building"></i>
                    Select Companies to Process
                </h2>
                <button class="btn btn-success" onclick="processVoucher()" id="processBtn" disabled>
                    <i class="fas fa-cogs"></i>
                    Process Selected
                </button>
            </div>
            
            <div class="company-grid" id="companyGrid">
                <!-- Companies will be loaded here -->
            </div>
            
            <button class="btn btn-secondary" onclick="showPreview()">
                <i class="fas fa-arrow-left"></i>
                Back to Preview
            </button>
        </div>

        <!-- Step 4: Processing Results -->
        <div id="processStep" class="section d-none">
            <div class="d-flex" style="justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 class="section-title mb-0">
                    <i class="fas fa-check-circle"></i>
                    Processing Complete
                </h2>
                <button class="btn btn-primary" onclick="showReportGeneration()">
                    Generate Report <i class="fas fa-arrow-right"></i>
                </button>
            </div>
            
            <div class="stats-grid" id="processingStats">
                <!-- Processing stats will be loaded here -->
            </div>
        </div>

        <!-- Step 5: Generate Report -->
        <div id="reportStep" class="section d-none">
            <h2 class="section-title">
                <i class="fas fa-file-alt"></i>
                Generate Capital Transport Report
            </h2>
            
            <form id="reportForm" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <div class="mb-2">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Company</label>
                        <select id="reportCompany" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-light); border-radius: 8px;" required>
                            <option value="">Select company...</option>
                        </select>
                    </div>
                    
                    <div class="mb-2">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Week Start</label>
                        <input type="date" id="weekStart" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-light); border-radius: 8px;" required>
                    </div>
                    
                    <div class="mb-2">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Week End</label>
                        <input type="date" id="weekEnd" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-light); border-radius: 8px;" required>
                    </div>
                    
                    <div class="mb-2">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Payment Date</label>
                        <input type="date" id="paymentDate" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-light); border-radius: 8px;">
                    </div>
                    
                    <div class="mb-2">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">YTD Amount</label>
                        <input type="number" id="ytdAmount" step="0.01" value="0.00" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-light); border-radius: 8px;">
                    </div>
                </div>
                
                <div>
                    <h3 style="margin-bottom: 1rem;">Report Preview</h3>
                    <div id="reportPreview" style="background: var(--light-gray); padding: 1.5rem; border-radius: 8px;">
                        <p style="color: var(--text-muted);">Select a company to see preview</p>
                    </div>
                </div>
            </form>
            
            <div class="d-flex" style="gap: 1rem; margin-top: 2rem; justify-content: space-between;">
                <button type="button" class="btn btn-secondary" onclick="showProcessResults()">
                    <i class="fas fa-arrow-left"></i>
                    Back
                </button>
                <button type="button" class="btn btn-success" onclick="generateReport()">
                    <i class="fas fa-download"></i>
                    Generate Report
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Global variables
        let currentVoucherId = null;
        let previewData = null;
        let availableCompanies = [
            { id: 1, name: 'Johnson & Associates LLC', identifier: 'JAV', capital_percentage: 5.0 },
            { id: 2, name: 'Martin Construction Company', identifier: 'MAR', capital_percentage: 4.5 },
            { id: 3, name: 'Brown Transport Solutions', identifier: 'BRN', capital_percentage: 6.0 },
            { id: 4, name: 'Wilson Logistics Corp', identifier: 'WIL', capital_percentage: 5.5 }
        ];
        let selectedCompanies = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setDefaultDates();
        });

        function setupEventListeners() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('fileInput');

            uploadZone.addEventListener('click', () => fileInput.click());
            uploadZone.addEventListener('dragover', handleDragOver);
            uploadZone.addEventListener('dragleave', handleDragLeave);
            uploadZone.addEventListener('drop', handleDrop);
            
            fileInput.addEventListener('change', handleFileSelect);
            document.getElementById('reportCompany').addEventListener('change', updateReportPreview);
        }

        function handleDragOver(e) {
            e.preventDefault();
            document.getElementById('uploadZone').classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            document.getElementById('uploadZone').classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            document.getElementById('uploadZone').classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                processFile(files[0]);
            }
        }

        function handleFileSelect(e) {
            if (e.target.files.length > 0) {
                processFile(e.target.files[0]);
            }
        }

        function processFile(file) {
            if (!validateFile(file)) return;
            
            document.getElementById('uploadProgress').style.display = 'block';
            
            // Simulate upload progress
            let progress = 0;
            const progressFill = document.getElementById('progressFill');
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 100) progress = 100;
                progressFill.style.width = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(interval);
                    setTimeout(() => {
                        currentVoucherId = Math.floor(Math.random() * 1000);
                        showAlert('success', 'File uploaded successfully!');
                        previewVoucher();
                    }, 500);
                }
            }, 200);
        }

        function validateFile(file) {
            const allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
            const maxSize = 20 * 1024 * 1024; // 20MB
            
            if (!allowedTypes.includes(file.type)) {
                showAlert('error', 'Invalid file type. Please use PDF or Excel files.');
                return false;
            }
            
            if (file.size > maxSize) {
                showAlert('error', 'File too large. Maximum size is 20MB.');
                return false;
            }
            
            return true;
        }

        function previewVoucher() {
            setTimeout(() => {
                updateStep(2);
                showStep('previewStep');
                
                // Simulate preview data
                document.getElementById('totalRows').textContent = '25';
                document.getElementById('validRows').textContent = '23';
                document.getElementById('companiesFound').textContent = '3';
                
                // Show sample data
                const tbody = document.getElementById('previewTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td>07/14/2025</td>
                        <td>PLANT 001</td>
                        <td>H2648318</td>
                        <td>$10.60</td>
                        <td>21.56</td>
                        <td>$228.54</td>
                        <td>RMTJAV001</td>
                        <td><span style="background: var(--primary-red); color: var(--white); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem;">JAV</span></td>
                    </tr>
                    <tr>
                        <td>07/15/2025</td>
                        <td>PLANT 002</td>
                        <td>H2648319</td>
                        <td>$12.30</td>
                        <td>18.75</td>
                        <td>$230.63</td>
                        <td>RMTMAR002</td>
                        <td><span style="background: var(--primary-red); color: var(--white); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem;">MAR</span></td>
                    </tr>
                    <tr>
                        <td>07/16/2025</td>
                        <td>PLANT 003</td>
                        <td>H2648320</td>
                        <td>$11.45</td>
                        <td>20.30</td>
                        <td>$232.44</td>
                        <td>RMTBRN003</td>
                        <td><span style="background: var(--primary-red); color: var(--white); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem;">BRN</span></td>
                    </tr>
                `;
                
                loadCompanySelection();
            }, 1000);
        }

        function loadCompanySelection() {
            const companyGrid = document.getElementById('companyGrid');
            companyGrid.innerHTML = '';
            
            availableCompanies.forEach(company => {
                const companyCard = document.createElement('div');
                companyCard.className = 'company-card';
                companyCard.innerHTML = `
                    <div class="company-header">
                        <input type="checkbox" class="company-checkbox" value="${company.identifier}" 
                               id="company_${company.id}" onchange="updateSelectedCompanies()">
                        <div style="flex: 1;">
                            <div class="company-name">${company.name}</div>
                            <span class="company-identifier">${company.identifier}</span>
                            <div style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">
                                Capital Percentage: <span style="color: var(--primary-red); font-weight: 600;">${company.capital_percentage}%</span>
                            </div>
                        </div>
                    </div>
                `;
                companyGrid.appendChild(companyCard);
                
                // Add click handler to card
                companyCard.addEventListener('click', (e) => {
                    if (e.target.type !== 'checkbox') {
                        const checkbox = companyCard.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;
                        updateSelectedCompanies();
                    }
                });
            });
        }

        function updateSelectedCompanies() {
            selectedCompanies = [];
            document.querySelectorAll('.company-checkbox:checked').forEach(cb => {
                selectedCompanies.push(cb.value);
            });
            
            // Update visual state
            document.querySelectorAll('.company-card').forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
            
            document.getElementById('processBtn').disabled = selectedCompanies.length === 0;
        }

        function processVoucher() {
            if (selectedCompanies.length === 0) {
                showAlert('warning', 'Please select at least one company');
                return;
            }
            
            showAlert('info', 'Processing voucher data...');
            
            // Simulate processing
            setTimeout(() => {
                const savedTrips = selectedCompanies.length * 5; // Simulate trips per company
                const filteredRows = selectedCompanies.length * 6;
                
                displayProcessingResults({
                    saved_trips: savedTrips,
                    filtered_rows: filteredRows,
                    companies_processed: selectedCompanies.length
                });
                
                updateStep(4);
                showStep('processStep');
                
                showAlert('success', 'Voucher processed successfully!');
            }, 2000);
        }

        function displayProcessingResults(data) {
            const statsContainer = document.getElementById('processingStats');
            statsContainer.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--success-green);">${data.saved_trips}</div>
                    <div class="stat-label">Trips Saved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--primary-red);">${data.filtered_rows}</div>
                    <div class="stat-label">Rows Processed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--warning-orange);">${data.companies_processed}</div>
                    <div class="stat-label">Companies</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--success-green);">100%</div>
                    <div class="stat-label">Complete</div>
                </div>
            `;
            
            // Load companies for report generation
            const reportCompanySelect = document.getElementById('reportCompany');
            reportCompanySelect.innerHTML = '<option value="">Select company...</option>';
            
            selectedCompanies.forEach(identifier => {
                const company = availableCompanies.find(c => c.identifier === identifier);
                if (company) {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.dataset.identifier = company.identifier;
                    option.dataset.percentage = company.capital_percentage;
                    option.textContent = `${company.name} (${company.identifier})`;
                    reportCompanySelect.appendChild(option);
                }
            });
        }

        function updateReportPreview() {
            const select = document.getElementById('reportCompany');
            const previewDiv = document.getElementById('reportPreview');
            
            if (!select.value) {
                previewDiv.innerHTML = '<p style="color: var(--text-muted);">Select a company to see preview</p>';
                return;
            }
            
            const option = select.selectedOptions[0];
            const companyName = option.textContent.split('(')[0].trim();
            const identifier = option.dataset.identifier;
            const percentage = option.dataset.percentage;
            
            previewDiv.innerHTML = `
                <h4 style="color: var(--primary-red); margin-bottom: 1rem;">CAPITAL TRANSPORT LLP</h4>
                <h5 style="color: var(--dark-gray); margin-bottom: 1.5rem;">PAYMENT INFORMATION</h5>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div><strong>Company:</strong> ${companyName}</div>
                    <div><strong>Identifier:</strong> <span style="background: var(--primary-red); color: var(--white); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.8rem;">${identifier}</span></div>
                    <div><strong>Capital %:</strong> <span style="color: var(--primary-red); font-weight: 600;">${percentage}%</span></div>
                    <div><strong>Payment No:</strong> <span style="color: var(--success-green); font-weight: 600;">Auto-generated</span></div>
                </div>
                
                <div style="background: var(--light-gray); padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                    <small style="color: var(--text-muted);">
                        <i class="fas fa-info-circle"></i>
                        Financial calculations will be performed automatically based on processed trip data.
                    </small>
                </div>
            `;
        }

        function generateReport() {
            const companyId = document.getElementById('reportCompany').value;
            const weekStart = document.getElementById('weekStart').value;
            const weekEnd = document.getElementById('weekEnd').value;
            const paymentDate = document.getElementById('paymentDate').value;
            const ytdAmount = document.getElementById('ytdAmount').value;
            
            if (!companyId) {
                showAlert('warning', 'Please select a company');
                return;
            }
            
            if (!weekStart || !weekEnd) {
                showAlert('warning', 'Please select week start and end dates');
                return;
            }
            
            showAlert('info', 'Generating Capital Transport report...');
            
            // Simulate report generation
            setTimeout(() => {
                const selectedCompany = availableCompanies.find(c => c.id == companyId);
                const paymentNo = Math.floor(Math.random() * 50) + 1;
                
                showAlert('success', `Report generated successfully for ${selectedCompany.name}! Payment No: ${paymentNo}`);
                
                // Show completion and reset workflow
                setTimeout(() => {
                    showAlert('info', 'Ready to process another voucher. Upload a new file to begin.');
                    
                    setTimeout(() => {
                        resetWorkflow();
                    }, 3000);
                }, 2000);
                
            }, 2000);
        }

        function resetWorkflow() {
            currentVoucherId = null;
            previewData = null;
            selectedCompanies = [];
            
            updateStep(1);
            showStep('uploadStep');
            
            // Clear form data
            document.getElementById('fileInput').value = '';
            document.getElementById('previewTableBody').innerHTML = '';
            document.getElementById('companyGrid').innerHTML = '';
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('progressFill').style.width = '0%';
            
            showAlert('info', 'Workflow reset. Ready for new voucher upload.');
        }

        // Navigation functions
        function updateStep(step) {
            document.querySelectorAll('.step').forEach((el, index) => {
                el.classList.remove('active', 'completed');
                if (index + 1 < step) {
                    el.classList.add('completed');
                } else if (index + 1 === step) {
                    el.classList.add('active');
                }
            });
        }

        function showStep(stepId) {
            document.querySelectorAll('[id$="Step"]').forEach(el => {
                el.classList.add('d-none');
            });
            document.getElementById(stepId).classList.remove('d-none');
        }

        function showPreview() { 
            updateStep(2); 
            showStep('previewStep'); 
        }

        function showCompanySelection() { 
            updateStep(3); 
            showStep('companyStep'); 
        }

        function showProcessResults() { 
            updateStep(4); 
            showStep('processStep'); 
        }

        function showReportGeneration() { 
            updateStep(5); 
            showStep('reportStep'); 
        }

        // Utility functions
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert_' + Date.now();
            
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-triangle',
                warning: 'fas fa-exclamation-circle',
                info: 'fas fa-info-circle'
            };
            
            const alertHTML = `
                <div id="${alertId}" class="alert alert-${type}">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <i class="${icons[type]}"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" onclick="removeAlert('${alertId}')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7;">&times;</button>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('beforeend', alertHTML);
            
            setTimeout(() => removeAlert(alertId), 5000);
        }

        function removeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) alert.remove();
        }

        function setDefaultDates() {
            const today = new Date();
            const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            
            document.getElementById('weekStart').value = weekStart.toISOString().split('T')[0];
            document.getElementById('weekEnd').value = weekEnd.toISOString().split('T')[0];
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                if (confirm('Reset the current workflow?')) {
                    resetWorkflow();
                }
            }
            
            if (e.key === 'Escape') {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => alert.remove());
            }
        });

        // Error handling
        window.addEventListener('error', function(e) {
            console.error('Error:', e.error);
            showAlert('error', 'An unexpected error occurred. Please refresh the page.');
        });

        // Prevent data loss
        window.addEventListener('beforeunload', function(e) {
            if (currentVoucherId && selectedCompanies.length > 0) {
                e.preventDefault();
                e.returnValue = 'You have unprocessed data. Are you sure you want to leave?';
            }
        });
    </script>
</body>
</html>