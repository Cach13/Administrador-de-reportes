<?php
/**
 * Company & Vehicle Management - Capital Transport
 * Ruta: /pages/companies.php
 */
require_once '../includes/auth-check.php';
require_once '../config/config.php';
require_once '../classes/Database.php';

$db = Database::getInstance();
$pageTitle = 'Company Management';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Capital Transport</title>
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
            --info-blue: #3b82f6;
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

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-link {
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            text-decoration: none;
        }

        .nav-link.active {
            background: var(--primary-red);
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
            color: var(--white);
            text-decoration: none;
        }

        /* MAIN CONTENT */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark-gray);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            color: var(--primary-red);
        }

        .page-actions {
            display: flex;
            gap: 1rem;
        }

        /* SECTIONS */
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

        /* FORMS */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .form-label.required::after {
            content: '*';
            color: var(--error-red);
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-control:focus {
            border-color: var(--primary-red);
            outline: none;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .form-control.success {
            border-color: var(--success-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-control.error {
            border-color: var(--error-red);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .form-help.error {
            color: var(--error-red);
        }

        .form-help.success {
            color: var(--success-green);
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
            font-size: 0.95rem;
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

        .btn-danger {
            background: var(--error-red);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #dc2626;
            color: var(--white);
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }

        /* VEHICLE MANAGEMENT */
        .vehicle-section {
            background: var(--light-gray);
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .vehicle-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .vehicle-add-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .vehicle-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .vehicle-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--white);
            border-radius: 8px;
            border-left: 4px solid var(--primary-red);
            transition: all 0.3s ease;
        }

        .vehicle-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .vehicle-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .vehicle-id {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--dark-gray);
            background: var(--light-gray);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 1rem;
            letter-spacing: 1px;
        }

        .vehicle-company-id {
            background: var(--primary-red);
            color: var(--white);
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .vehicle-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* COMPANIES TABLE */
        .companies-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .companies-table th {
            background: var(--dark-gray);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .companies-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .companies-table tr:hover {
            background: #f8f9fa;
        }

        .company-name {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.25rem;
        }

        .company-details {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .company-identifier {
            background: var(--primary-red);
            color: var(--white);
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .vehicle-count {
            background: var(--info-blue);
            color: var(--white);
            padding: 0.25rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
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

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--info-blue);
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }

        /* ANIMATIONS */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .main-content {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .page-actions {
                justify-content: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .vehicle-add-form {
                flex-direction: column;
                align-items: stretch;
            }

            .vehicle-item {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .companies-table {
                font-size: 0.85rem;
            }

            .companies-table th,
            .companies-table td {
                padding: 0.5rem;
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
                    <p>Company Management System</p>
                </div>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="companies.php" class="nav-link active">
                    <i class="fas fa-building"></i>
                    Companies
                </a>
                <a href="vouchers.php" class="nav-link">
                    <i class="fas fa-file-invoice"></i>
                    Vouchers
                </a>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Reports
                </a>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-building"></i>
                Company Management
            </h1>
            <div class="page-actions">
                <button class="btn btn-secondary" onclick="refreshData()">
                    <i class="fas fa-sync"></i>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Add New Company Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-plus-circle"></i>
                    Add New Company
                </div>
                <button class="btn btn-secondary btn-sm" onclick="resetForm()">
                    <i class="fas fa-broom"></i>
                    Clear Form
                </button>
            </div>
            <div class="section-content">
                <form id="companyForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required">Company Name</label>
                            <input type="text" class="form-control" id="companyName" placeholder="e.g., Johnson & Associates LLC" required>
                            <div class="form-help">Full legal name of the transport company</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Company Identifier</label>
                            <input type="text" class="form-control" id="companyIdentifier" placeholder="JAV" maxlength="3" required>
                            <div class="form-help" id="identifierHelp">3-letter identifier extracted from Vehicle ID (chars 4-6)</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Capital Percentage</label>
                            <input type="number" class="form-control" id="capitalPercentage" placeholder="5.00" step="0.01" min="0" max="100" required>
                            <div class="form-help">Percentage deducted from total payments</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contactPerson" placeholder="John Doe">
                            <div class="form-help">Primary contact for this company</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" placeholder="+1 (555) 123-4567">
                            <div class="form-help">Contact phone number</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" placeholder="contact@company.com">
                            <div class="form-help">Primary email address</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success" id="saveBtn">
                        <i class="fas fa-save"></i>
                        Save Company
                    </button>
                </form>
            </div>
        </div>

        <!-- Vehicle Management Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-truck"></i>
                    Vehicle Management
                </div>
            </div>
            <div class="section-content">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Select Company for Vehicle Management</label>
                    <select class="form-control" id="vehicleCompanySelect" onchange="loadCompanyVehicles()">
                        <option value="">Choose a company to manage its vehicles...</option>
                    </select>
                    <div class="form-help">Select a company to add, view, and manage its vehicles</div>
                </div>
                
                <div id="vehicleManagement" class="vehicle-section d-none">
                    <div class="vehicle-header">
                        <h3>Manage Vehicles for <span id="selectedCompanyName">Company</span></h3>
                        <span class="vehicle-count" id="vehicleCountBadge">0 vehicles</span>
                    </div>
                    
                    <div class="vehicle-add-form">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label class="form-label required">Vehicle ID (9 characters)</label>
                            <input type="text" class="form-control" id="vehicleId" placeholder="RMTJAV001" maxlength="9" required>
                            <div class="form-help" id="vehicleIdHelp">Format: XXX<span style="background: var(--primary-red); color: var(--white); padding: 0.1rem 0.3rem; border-radius: 3px;">ABC</span>123</div>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="addVehicle()">
                            <i class="fas fa-plus"></i>
                            Add Vehicle
                        </button>
                    </div>
                    
                    <div id="vehicleList" class="vehicle-list">
                        <!-- Vehicles will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Companies Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-list"></i>
                    Registered Companies
                </div>
                <div>
                    <span id="companiesCount" class="badge" style="background: var(--white); color: var(--primary-red); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600;">
                        Loading...
                    </span>
                </div>
            </div>
            <div class="section-content">
                <div id="companiesTableContainer">
                    <table class="companies-table">
                        <thead>
                            <tr>
                                <th>Company Information</th>
                                <th>Identifier</th>
                                <th>Capital %</th>
                                <th>Vehicles</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="companiesTableBody">
                            <!-- Companies will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <div id="noCompanies" class="empty-state" style="display: none;">
                    <i class="fas fa-building"></i>
                    <h3>No Companies Registered</h3>
                    <p>Start by adding your first transport company above</p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Global variables
        let companies = [];
        let editingCompany = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            loadCompanies();
        });

        function setupEventListeners() {
            // Company form submission
            document.getElementById('companyForm').addEventListener('submit', handleCompanySubmit);
            
            // Real-time validation for company identifier
            document.getElementById('companyIdentifier').addEventListener('input', function(e) {
                validateCompanyIdentifier(e.target);
            });
            
            // Real-time validation for vehicle ID
            document.getElementById('vehicleId').addEventListener('input', function(e) {
                validateVehicleId(e.target);
            });
            
            // Capital percentage validation
            document.getElementById('capitalPercentage').addEventListener('input', function(e) {
                validateCapitalPercentage(e.target);
            });
        }

        // Company Management
        function handleCompanySubmit(e) {
            e.preventDefault();
            
            // Collect form data
            const formData = {
                name: document.getElementById('companyName').value.trim(),
                identifier: document.getElementById('companyIdentifier').value.trim().toUpperCase(),
                capital_percentage: parseFloat(document.getElementById('capitalPercentage').value),
                contact_person: document.getElementById('contactPerson').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                email: document.getElementById('email').value.trim()
            };
            
            // Validate required fields
            if (!formData.name || !formData.identifier || !formData.capital_percentage) {
                showAlert('error', 'Please fill in all required fields');
                return;
            }
            
            // Validate identifier length
            if (formData.identifier.length !== 3) {
                showAlert('error', 'Company identifier must be exactly 3 letters');
                return;
            }
            
            // Check if identifier already exists (only if not editing)
            if (!editingCompany && companies.find(c => c.identifier === formData.identifier)) {
                showAlert('error', 'Company identifier already exists!');
                return;
            }
            
            // Save company
            if (editingCompany) {
                updateCompany(editingCompany, formData);
            } else {
                createCompany(formData);
            }
        }

        function createCompany(formData) {
            // Simulate API call
            showAlert('info', 'Creating company...');
            
            setTimeout(() => {
                const newCompany = {
                    id: companies.length + 1,
                    ...formData,
                    vehicles: [],
                    created_at: new Date().toISOString()
                };
                
                companies.push(newCompany);
                
                showAlert('success', `Company "${formData.name}" created successfully!`);
                resetForm();
                loadCompanies();
                loadCompanySelects();
            }, 1000);
        }

        function updateCompany(companyId, formData) {
            const companyIndex = companies.findIndex(c => c.id === companyId);
            if (companyIndex === -1) return;
            
            showAlert('info', 'Updating company...');
            
            setTimeout(() => {
                companies[companyIndex] = {
                    ...companies[companyIndex],
                    ...formData
                };
                
                showAlert('success', `Company "${formData.name}" updated successfully!`);
                resetForm();
                loadCompanies();
                loadCompanySelects();
            }, 1000);
        }

        function editCompany(companyId) {
            const company = companies.find(c => c.id === companyId);
            if (!company) return;
            
            // Fill form with company data
            document.getElementById('companyName').value = company.name;
            document.getElementById('companyIdentifier').value = company.identifier;
            document.getElementById('capitalPercentage').value = company.capital_percentage;
            document.getElementById('contactPerson').value = company.contact_person || '';
            document.getElementById('phone').value = company.phone || '';
            document.getElementById('email').value = company.email || '';
            
            // Update form state
            editingCompany = companyId;
            document.getElementById('saveBtn').innerHTML = '<i class="fas fa-save"></i> Update Company';
            
            // Scroll to form
            document.getElementById('companyForm').scrollIntoView({ behavior: 'smooth' });
            
            showAlert('info', 'Company data loaded for editing');
        }

        function deleteCompany(companyId) {
            const company = companies.find(c => c.id === companyId);
            if (!company) return;
            
            if (!confirm(`Are you sure you want to delete "${company.name}"?\n\nThis will also remove all ${company.vehicles.length} vehicles associated with this company.`)) {
                return;
            }
            
            showAlert('info', 'Deleting company...');
            
            setTimeout(() => {
                const index = companies.findIndex(c => c.id === companyId);
                companies.splice(index, 1);
                
                showAlert('success', `Company "${company.name}" deleted successfully!`);
                loadCompanies();
                loadCompanySelects();
                
                // Clear vehicle management if this company was selected
                if (parseInt(document.getElementById('vehicleCompanySelect').value) === companyId) {
                    document.getElementById('vehicleCompanySelect').value = '';
                    document.getElementById('vehicleManagement').classList.add('d-none');
                }
            }, 1000);
        }

        function resetForm() {
            document.getElementById('companyForm').reset();
            editingCompany = null;
            document.getElementById('saveBtn').innerHTML = '<i class="fas fa-save"></i> Save Company';
            
            // Reset validation states
            document.querySelectorAll('.form-control').forEach(input => {
                input.classList.remove('success', 'error');
            });
            
            document.querySelectorAll('.form-help').forEach(help => {
                help.classList.remove('success', 'error');
            });
        }

        // Vehicle Management
        function loadCompanySelects() {
            const select = document.getElementById('vehicleCompanySelect');
            const currentValue = select.value;
            
            select.innerHTML = '<option value="">Choose a company to manage its vehicles...</option>';
            
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = `${company.name} (${company.identifier})`;
                select.appendChild(option);
            });
            
            // Restore selection if possible
            if (currentValue && companies.find(c => c.id == currentValue)) {
                select.value = currentValue;
            }
        }

        function loadCompanyVehicles() {
            const companyId = parseInt(document.getElementById('vehicleCompanySelect').value);
            const vehicleManagement = document.getElementById('vehicleManagement');
            const vehicleList = document.getElementById('vehicleList');
            
            if (!companyId) {
                vehicleManagement.classList.add('d-none');
                return;
            }
            
            const company = companies.find(c => c.id === companyId);
            if (!company) return;
            
            vehicleManagement.classList.remove('d-none');
            
            // Update header
            document.getElementById('selectedCompanyName').textContent = company.name;
            document.getElementById('vehicleCountBadge').textContent = `${company.vehicles.length} vehicles`;
            
            // Clear vehicle input
            document.getElementById('vehicleId').value = '';
            document.getElementById('vehicleId').classList.remove('success', 'error');
            
            // Load vehicles list
            vehicleList.innerHTML = '';
            
            if (company.vehicles.length === 0) {
                vehicleList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-truck"></i>
                        <h4>No Vehicles Registered</h4>
                        <p>Add the first vehicle for ${company.name} using the form above</p>
                    </div>
                `;
                return;
            }
            
            company.vehicles.forEach((vehicleId, index) => {
                const vehicleItem = document.createElement('div');
                vehicleItem.className = 'vehicle-item';
                vehicleItem.innerHTML = `
                    <div class="vehicle-info">
                        <div class="vehicle-id">${vehicleId}</div>
                        <div class="vehicle-company-id">${vehicleId.substring(3, 6)}</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                            Added: ${new Date().toLocaleDateString()}
                        </div>
                    </div>
                    <div class="vehicle-actions">
                        <button class="btn btn-danger btn-sm" onclick="removeVehicle(${companyId}, ${index})" title="Remove Vehicle">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                vehicleList.appendChild(vehicleItem);
            });
        }

        function addVehicle() {
            const companyId = parseInt(document.getElementById('vehicleCompanySelect').value);
            const vehicleId = document.getElementById('vehicleId').value.trim().toUpperCase();
            
            if (!companyId) {
                showAlert('warning', 'Please select a company first');
                return;
            }
            
            if (!vehicleId || vehicleId.length !== 9) {
                showAlert('error', 'Vehicle ID must be exactly 9 characters');
                return;
            }
            
            const company = companies.find(c => c.id === companyId);
            if (!company) return;
            
            // Extract company identifier from vehicle ID (chars 4-6)
            const vehicleCompanyId = vehicleId.substring(3, 6);
            
            if (vehicleCompanyId !== company.identifier) {
                showAlert('error', `Vehicle ID company identifier "${vehicleCompanyId}" doesn't match selected company "${company.identifier}"`);
                return;
            }
            
            // Check if vehicle already exists in this company
            if (company.vehicles.includes(vehicleId)) {
                showAlert('warning', 'This Vehicle ID already exists for this company');
                return;
            }
            
            // Check if vehicle exists in any other company
            const existingCompany = companies.find(c => c.vehicles.includes(vehicleId));
            if (existingCompany) {
                showAlert('error', `Vehicle ID "${vehicleId}" already exists in company "${existingCompany.name}"`);
                return;
            }
            
            // Add vehicle
            company.vehicles.push(vehicleId);
            
            showAlert('success', `Vehicle "${vehicleId}" added successfully to ${company.name}!`);
            
            // Clear input and refresh
            document.getElementById('vehicleId').value = '';
            document.getElementById('vehicleId').classList.remove('success', 'error');
            
            loadCompanyVehicles();
            loadCompanies(); // Refresh company table
        }

        function removeVehicle(companyId, vehicleIndex) {
            const company = companies.find(c => c.id === companyId);
            if (!company) return;
            
            const vehicleId = company.vehicles[vehicleIndex];
            
            if (!confirm(`Are you sure you want to remove vehicle "${vehicleId}" from ${company.name}?`)) {
                return;
            }
            
            // Remove vehicle
            company.vehicles.splice(vehicleIndex, 1);
            
            showAlert('success', `Vehicle "${vehicleId}" removed from ${company.name}!`);
            
            loadCompanyVehicles();
            loadCompanies(); // Refresh company table
        }

        // Validation Functions
        function validateCompanyIdentifier(input) {
            const value = input.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
            input.value = value;
            
            const helpText = document.getElementById('identifierHelp');
            
            if (value.length === 0) {
                input.classList.remove('success', 'error');
                helpText.textContent = '3-letter identifier extracted from Vehicle ID (chars 4-6)';
                helpText.classList.remove('success', 'error');
            } else if (value.length === 3) {
                // Check if identifier already exists (only if not editing)
                const exists = companies.find(c => c.identifier === value && (!editingCompany || c.id !== editingCompany));
                
                if (exists) {
                    input.classList.remove('success');
                    input.classList.add('error');
                    helpText.textContent = `Identifier "${value}" already exists!`;
                    helpText.classList.remove('success');
                    helpText.classList.add('error');
                } else {
                    input.classList.remove('error');
                    input.classList.add('success');
                    helpText.textContent = `✓ Identifier "${value}" is available`;
                    helpText.classList.remove('error');
                    helpText.classList.add('success');
                }
            } else {
                input.classList.remove('success', 'error');
                helpText.textContent = `${value.length}/3 characters entered`;
                helpText.classList.remove('success', 'error');
            }
        }

        function validateVehicleId(input) {
            const value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 9);
            input.value = value;
            
            const helpText = document.getElementById('vehicleIdHelp');
            const companyId = parseInt(document.getElementById('vehicleCompanySelect').value);
            
            if (value.length === 0) {
                input.classList.remove('success', 'error');
                helpText.innerHTML = 'Format: XXX<span style="background: var(--primary-red); color: var(--white); padding: 0.1rem 0.3rem; border-radius: 3px;">ABC</span>123';
                helpText.classList.remove('success', 'error');
            } else if (value.length === 9 && companyId) {
                const company = companies.find(c => c.id === companyId);
                const vehicleCompanyId = value.substring(3, 6);
                
                if (company && vehicleCompanyId === company.identifier) {
                    // Check if vehicle already exists
                    const existingCompany = companies.find(c => c.vehicles.includes(value));
                    
                    if (existingCompany) {
                        input.classList.remove('success');
                        input.classList.add('error');
                        helpText.textContent = `✗ Vehicle already exists in "${existingCompany.name}"`;
                        helpText.classList.remove('success');
                        helpText.classList.add('error');
                    } else {
                        input.classList.remove('error');
                        input.classList.add('success');
                        helpText.textContent = `✓ Valid Vehicle ID for ${company.name}`;
                        helpText.classList.remove('error');
                        helpText.classList.add('success');
                    }
                } else if (company) {
                    input.classList.remove('success');
                    input.classList.add('error');
                    helpText.textContent = `✗ Company ID "${vehicleCompanyId}" doesn't match "${company.identifier}"`;
                    helpText.classList.remove('success');
                    helpText.classList.add('error');
                }
            } else {
                input.classList.remove('success', 'error');
                if (value.length >= 6) {
                    const vehicleCompanyId = value.substring(3, 6);
                    helpText.innerHTML = `${value.length}/9 characters - Company ID: <span style="background: var(--primary-red); color: var(--white); padding: 0.1rem 0.3rem; border-radius: 3px;">${vehicleCompanyId}</span>`;
                } else {
                    helpText.textContent = `${value.length}/9 characters entered`;
                }
                helpText.classList.remove('success', 'error');
            }
        }

        function validateCapitalPercentage(input) {
            const value = parseFloat(input.value);
            
            if (isNaN(value) || value < 0 || value > 100) {
                input.classList.remove('success');
                input.classList.add('error');
            } else {
                input.classList.remove('error');
                input.classList.add('success');
            }
        }

        // Data Loading
        function loadCompanies() {
            const tbody = document.getElementById('companiesTableBody');
            const noCompanies = document.getElementById('noCompanies');
            const companiesCount = document.getElementById('companiesCount');
            const tableContainer = document.getElementById('companiesTableContainer');
            
            // Update count
            companiesCount.textContent = `${companies.length} companies`;
            
            if (companies.length === 0) {
                tableContainer.style.display = 'none';
                noCompanies.style.display = 'block';
                return;
            }
            
            tableContainer.style.display = 'block';
            noCompanies.style.display = 'none';
            
            tbody.innerHTML = '';
            
            companies.forEach(company => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="company-name">${company.name}</div>
                        <div class="company-details">${company.email || 'No email provided'}</div>
                    </td>
                    <td>
                        <span class="company-identifier">${company.identifier}</span>
                    </td>
                    <td>
                        <strong>${company.capital_percentage}%</strong>
                    </td>
                    <td>
                        <span class="vehicle-count">${company.vehicles.length} vehicles</span>
                    </td>
                    <td>
                        <div style="font-weight: 600;">${company.contact_person || 'No contact'}</div>
                        <div class="company-details">${company.phone || ''}</div>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-secondary btn-sm" onclick="editCompany(${company.id})" title="Edit Company">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteCompany(${company.id})" title="Delete Company">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function refreshData() {
            showAlert('info', 'Refreshing data...');
            
            setTimeout(() => {
                loadCompanies();
                loadCompanySelects();
                showAlert('success', 'Data refreshed successfully!');
            }, 1000);
        }

        // Utility Functions
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
            
            // Auto-remove after 5 seconds
            setTimeout(() => removeAlert(alertId), 5000);
        }

        function removeAlert(alertId) {
            const alert = document.getElementById(alertId);
            if (alert) alert.remove();
        }

        // Initialize with sample data
        function initializeSampleData() {
            if (companies.length === 0) {
                companies = [
                    {
                        id: 1,
                        name: 'Johnson & Associates LLC',
                        identifier: 'JAV',
                        capital_percentage: 5.0,
                        contact_person: 'John Johnson',
                        phone: '+1 (555) 123-4567',
                        email: 'john@johnson-associates.com',
                        vehicles: ['RMTJAV001', 'RMTJAV002', 'RMTJAV003'],
                        created_at: '2024-01-15T10:30:00Z'
                    },
                    {
                        id: 2,
                        name: 'Martin Construction Company',
                        identifier: 'MAR',
                        capital_percentage: 4.5,
                        contact_person: 'Maria Martin',
                        phone: '+1 (555) 234-5678',
                        email: 'maria@martinconstruction.com',
                        vehicles: ['RMTMAR001', 'RMTMAR002'],
                        created_at: '2024-01-20T14:15:00Z'
                    },
                    {
                        id: 3,
                        name: 'Brown Transport Solutions',
                        identifier: 'BRN',
                        capital_percentage: 6.0,
                        contact_person: 'Robert Brown',
                        phone: '+1 (555) 345-6789',
                        email: 'robert@browntransport.com',
                        vehicles: ['RMTBRN001', 'RMTBRN002', 'RMTBRN003', 'RMTBRN004'],
                        created_at: '2024-02-01T09:45:00Z'
                    }
                ];
                
                setTimeout(() => {
                    loadCompanies();
                    loadCompanySelects();
                }, 500);
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + N = New company (focus on name field)
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                document.getElementById('companyName').focus();
            }
            
            // Ctrl + R = Refresh data
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshData();
            }
            
            // Escape = Clear form
            if (e.key === 'Escape') {
                resetForm();
                document.querySelectorAll('.alert').forEach(alert => alert.remove());
            }
        });

        // Auto-save form data to prevent loss
        let autoSaveTimer;
        document.querySelectorAll('#companyForm input').forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    const formData = new FormData(document.getElementById('companyForm'));
                    const data = Object.fromEntries(formData);
                    sessionStorage.setItem('companyFormData', JSON.stringify(data));
                }, 1000);
            });
        });

        // Restore form data on page load
        function restoreFormData() {
            const savedData = sessionStorage.getItem('companyFormData');
            if (savedData && !editingCompany) {
                const data = JSON.parse(savedData);
                Object.keys(data).forEach(key => {
                    const element = document.getElementById(key);
                    if (element && data[key]) {
                        element.value = data[key];
                    }
                });
            }
        }

        // Initialize sample data after DOM loads
        setTimeout(() => {
            initializeSampleData();
            restoreFormData();
        }, 100);

        // Add utility class for hiding elements
        const style = document.createElement('style');
        style.textContent = '.d-none { display: none !important; }';
        document.head.appendChild(style);
    </script>
</body>
</html>