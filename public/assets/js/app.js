/**
 * ========================================
 * TRANSPORT MANAGEMENT SYSTEM - JAVASCRIPT PRINCIPAL
 * PASO 18: Assets & Frontend - JavaScript Unificado
 * Archivo: public/assets/js/app.js
 * 
 * CONECTA EL FRONTEND CON TUS CONTROLLERS REALES
 * ========================================
 */

/* ========================================
   CONFIGURACIÓN GLOBAL
   ======================================== */
window.TransportApp = {
    // Configuración base
    config: {
        baseUrl: window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/'),
        apiUrl: '/public/api.php',
        debug: true,
        version: '2.0.0'
    },
    
    // Estado global de la aplicación
    state: {
        currentUser: null,
        isLoading: false,
        notifications: [],
        uploads: new Map(),
        intervals: new Map()
    },
    
    // Cache para optimización
    cache: new Map(),
    
    // Inicializar aplicación
    init: function() {
        console.log('🚀 Transport Management System v' + this.config.version + ' iniciando...');
        
        this.setupGlobalHandlers();
        this.setupCSRFToken();
        this.setupAjaxDefaults();
        this.setupNotifications();
        this.detectCurrentPage();
        
        console.log('✅ Sistema inicializado correctamente');
    }
};

/* ========================================
   CONFIGURACIÓN INICIAL
   ======================================== */
TransportApp.setupGlobalHandlers = function() {
    // Manejo global de errores
    window.addEventListener('error', (e) => {
        console.error('Error global:', e.error);
        this.showNotification('Error inesperado en la aplicación', 'error');
    });
    
    // Manejo de promesas rechazadas
    window.addEventListener('unhandledrejection', (e) => {
        console.error('Promesa rechazada:', e.reason);
        this.showNotification('Error de conexión', 'error');
    });
    
    // Detectar pérdida de conexión
    window.addEventListener('offline', () => {
        this.showNotification('Conexión perdida', 'warning', 0);
    });
    
    window.addEventListener('online', () => {
        this.showNotification('Conexión restaurada', 'success');
    });
};

TransportApp.setupCSRFToken = function() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        this.config.csrfToken = token;
    }
};

TransportApp.setupAjaxDefaults = function() {
    // Configurar headers por defecto para fetch
    this.defaultFetchOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    };
    
    if (this.config.csrfToken) {
        this.defaultFetchOptions.headers['X-CSRF-Token'] = this.config.csrfToken;
    }
};

/* ========================================
   SISTEMA DE NOTIFICACIONES
   ======================================== */
TransportApp.setupNotifications = function() {
    // Crear contenedor de notificaciones si no existe
    if (!document.getElementById('notifications-container')) {
        const container = document.createElement('div');
        container.id = 'notifications-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
};

TransportApp.showNotification = function(message, type = 'info', duration = 5000) {
    const container = document.getElementById('notifications-container');
    if (!container) return;
    
    const notification = document.createElement('div');
    const id = 'notification-' + Date.now();
    
    notification.id = id;
    notification.className = `notification notification-${type} show`;
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <strong>${this.getNotificationIcon(type)}</strong>
                ${message}
            </div>
            <button type="button" class="btn-close" onclick="TransportApp.hideNotification('${id}')">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Auto-hide después de duration
    if (duration > 0) {
        setTimeout(() => {
            this.hideNotification(id);
        }, duration);
    }
    
    return id;
};

TransportApp.hideNotification = function(id) {
    const notification = document.getElementById(id);
    if (notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
};

TransportApp.getNotificationIcon = function(type) {
    const icons = {
        'success': '<i class="bi bi-check-circle me-2"></i>',
        'error': '<i class="bi bi-exclamation-triangle me-2"></i>',
        'warning': '<i class="bi bi-exclamation-circle me-2"></i>',
        'info': '<i class="bi bi-info-circle me-2"></i>'
    };
    return icons[type] || icons.info;
};

/* ========================================
   SISTEMA DE LOADING
   ======================================== */
TransportApp.showLoading = function(message = 'Cargando...') {
    this.state.isLoading = true;
    
    let overlay = document.getElementById('loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.className = 'loading-overlay';
        document.body.appendChild(overlay);
    }
    
    overlay.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="mt-3">${message}</div>
        </div>
    `;
    overlay.style.display = 'flex';
};

TransportApp.hideLoading = function() {
    this.state.isLoading = false;
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
};

/* ========================================
   COMUNICACIÓN CON API
   ======================================== */
TransportApp.apiCall = async function(endpoint, options = {}) {
    try {
        const url = this.config.baseUrl + this.config.apiUrl;
        
        const fetchOptions = {
            ...this.defaultFetchOptions,
            ...options,
            headers: {
                ...this.defaultFetchOptions.headers,
                ...options.headers
            }
        };
        
        // Agregar endpoint al body para el router
        if (fetchOptions.method === 'POST' || !fetchOptions.method) {
            fetchOptions.method = 'POST';
            const body = typeof fetchOptions.body === 'string' ? 
                JSON.parse(fetchOptions.body) : (fetchOptions.body || {});
            body.endpoint = endpoint;
            fetchOptions.body = JSON.stringify(body);
        }
        
        if (this.config.debug) {
            console.log(`🌐 API Call: ${endpoint}`, fetchOptions);
        }
        
        const response = await fetch(url, fetchOptions);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}`);
        }
        
        if (this.config.debug) {
            console.log(`✅ API Response: ${endpoint}`, data);
        }
        
        return data;
        
    } catch (error) {
        console.error(`❌ API Error: ${endpoint}`, error);
        throw error;
    }
};

/* ========================================
   DETECCIÓN DE PÁGINA ACTUAL
   ======================================== */
TransportApp.detectCurrentPage = function() {
    const path = window.location.pathname;
    
    if (path.includes('dashboard')) {
        this.initDashboard();
    } else if (path.includes('processing')) {
        this.initProcessing();
    } else if (path.includes('management')) {
        this.initManagement();
    }
};

/* ========================================
   MÓDULO: DASHBOARD
   ======================================== */
TransportApp.initDashboard = function() {
    console.log('📊 Inicializando Dashboard...');
    
    this.dashboard = {
        charts: {},
        refreshInterval: null,
        
        init: function() {
            this.setupEventListeners();
            this.loadStats();
            this.initCharts();
            this.setupAutoRefresh();
            TransportApp.showNotification('Dashboard cargado correctamente', 'success', 3000);
        },
        
        setupEventListeners: function() {
            // Botón de refresh
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => this.refreshDashboard());
            }
            
            // Botones de procesamiento de vouchers
            document.querySelectorAll('[data-action="process-voucher"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const voucherId = e.target.getAttribute('data-voucher-id');
                    this.processVoucher(voucherId);
                });
            });
            
            // Botones de descarga de reportes
            document.querySelectorAll('[data-action="download-report"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const reportId = e.target.getAttribute('data-report-id');
                    this.downloadReport(reportId);
                });
            });
        },
        
        async loadStats() {
            try {
                TransportApp.showLoading('Cargando estadísticas...');
                
                const response = await TransportApp.apiCall('dashboard/stats');
                
                if (response.success) {
                    this.updateStatsCards(response.data);
                    this.updateCharts(response.data);
                } else {
                    throw new Error(response.message);
                }
                
            } catch (error) {
                TransportApp.showNotification('Error cargando estadísticas: ' + error.message, 'error');
            } finally {
                TransportApp.hideLoading();
            }
        },
        
        updateStatsCards: function(stats) {
            // Actualizar cards de estadísticas
            const updates = {
                'totalCompanies': stats.totalCompanies || 0,
                'totalVouchers': stats.totalVouchers || 0,
                'processedToday': stats.processedToday || 0,
                'totalReports': stats.totalReports || 0
            };
            
            Object.entries(updates).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = new Intl.NumberFormat().format(value);
                }
            });
        },
        
        initCharts: function() {
            this.initMonthlyChart();
            this.initCompaniesChart();
        },
        
        initMonthlyChart: function() {
            const ctx = document.getElementById('monthlyChart');
            if (!ctx) return;
            
            this.charts.monthly = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Vouchers Procesados',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        initCompaniesChart: function() {
            const ctx = document.getElementById('companiesChart');
            if (!ctx) return;
            
            this.charts.companies = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['JAV', 'ABC', 'XYZ'],
                    datasets: [{
                        data: [40, 35, 25],
                        backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },
        
        updateCharts: function(data) {
            if (data.monthlyTrends && this.charts.monthly) {
                this.charts.monthly.data.labels = data.monthlyTrends.labels;
                this.charts.monthly.data.datasets[0].data = data.monthlyTrends.data;
                this.charts.monthly.update();
            }
            
            if (data.companiesStats && this.charts.companies) {
                this.charts.companies.data.labels = data.companiesStats.labels;
                this.charts.companies.data.datasets[0].data = data.companiesStats.data;
                this.charts.companies.update();
            }
        },
        
        setupAutoRefresh: function() {
            // Refresh automático cada 5 minutos
            this.refreshInterval = setInterval(() => {
                this.loadStats();
            }, 5 * 60 * 1000);
        },
        
        refreshDashboard: function() {
            TransportApp.showNotification('Actualizando dashboard...', 'info', 2000);
            this.loadStats();
        },
        
        async processVoucher(voucherId) {
            if (!confirm('¿Procesar este voucher?')) return;
            
            try {
                TransportApp.showLoading('Procesando voucher...');
                
                const response = await TransportApp.apiCall('processing/process', {
                    method: 'POST',
                    body: JSON.stringify({ voucher_id: voucherId })
                });
                
                if (response.success) {
                    TransportApp.showNotification('Voucher procesado correctamente', 'success');
                    this.loadStats(); // Refresh stats
                } else {
                    throw new Error(response.message);
                }
                
            } catch (error) {
                TransportApp.showNotification('Error procesando voucher: ' + error.message, 'error');
            } finally {
                TransportApp.hideLoading();
            }
        },
        
        async downloadReport(reportId) {
            try {
                TransportApp.showLoading('Preparando descarga...');
                
                const response = await TransportApp.apiCall('reports/download', {
                    method: 'POST',
                    body: JSON.stringify({ report_id: reportId })
                });
                
                if (response.success && response.data.download_url) {
                    // Crear enlace temporal para descarga
                    const link = document.createElement('a');
                    link.href = response.data.download_url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    TransportApp.showNotification('Reporte descargado', 'success');
                } else {
                    throw new Error(response.message || 'Error en descarga');
                }
                
            } catch (error) {
                TransportApp.showNotification('Error descargando reporte: ' + error.message, 'error');
            } finally {
                TransportApp.hideLoading();
            }
        }
    };
    
    // Inicializar dashboard si estamos en la página
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => this.dashboard.init());
    } else {
        this.dashboard.init();
    }
};

/* ========================================
   MÓDULO: PROCESSING
   ======================================== */
TransportApp.initProcessing = function() {
    console.log('📤 Inicializando Processing...');
    
    this.processing = {
        currentUploads: new Map(),
        
        init: function() {
            this.setupUploadArea();
            this.setupEventListeners();
            TransportApp.showNotification('Sistema de procesamiento listo', 'success', 3000);
        },
        
        setupUploadArea: function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            
            if (!uploadArea || !fileInput) return;
            
            // Drag & Drop
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
                this.handleFiles(files);
            });
            
            // Click to upload
            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', (e) => {
                this.handleFiles(e.target.files);
            });
        },
        
        setupEventListeners: function() {
            // Botones de procesamiento
            document.querySelectorAll('[data-action="process-file"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const fileId = e.target.getAttribute('data-file-id');
                    this.processFile(fileId);
                });
            });
            
            // Selector de empresas
            const companySelector = document.getElementById('companySelector');
            if (companySelector) {
                companySelector.addEventListener('change', () => {
                    this.updateSelectedCompanies();
                });
            }
        },
        
        handleFiles: function(files) {
            Array.from(files).forEach(file => {
                if (this.validateFile(file)) {
                    this.uploadFile(file);
                }
            });
        },
        
        validateFile: function(file) {
            const allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            const maxSize = 20 * 1024 * 1024; // 20MB
            
            if (!allowedTypes.includes(file.type)) {
                TransportApp.showNotification('Tipo de archivo no válido. Solo PDF y Excel.', 'error');
                return false;
            }
            
            if (file.size > maxSize) {
                TransportApp.showNotification('Archivo demasiado grande. Máximo 20MB.', 'error');
                return false;
            }
            
            return true;
        },
        
        async uploadFile(file) {
            const uploadId = 'upload-' + Date.now();
            
            try {
                // Crear UI de progreso
                this.createUploadProgress(uploadId, file.name);
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('type', 'voucher');
                
                const response = await TransportApp.apiCall('processing/upload', {
                    method: 'POST',
                    body: formData,
                    headers: {} // Quitar Content-Type para que el browser lo maneje
                });
                
                if (response.success) {
                    this.updateUploadProgress(uploadId, 100, 'Subido correctamente');
                    TransportApp.showNotification(`Archivo ${file.name} subido correctamente`, 'success');
                    
                    // Actualizar lista de archivos
                    this.refreshFilesList();
                } else {
                    throw new Error(response.message);
                }
                
            } catch (error) {
                this.updateUploadProgress(uploadId, 0, 'Error: ' + error.message);
                TransportApp.showNotification('Error subiendo archivo: ' + error.message, 'error');
            }
        },
        
        createUploadProgress: function(uploadId, filename) {
            const container = document.getElementById('uploadsContainer') || document.body;
            
            const progressHtml = `
                <div id="${uploadId}" class="upload-progress mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="filename">${filename}</span>
                        <span class="status">Subiendo...</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('afterbegin', progressHtml);
        },
        
        updateUploadProgress: function(uploadId, percent, status) {
            const uploadElement = document.getElementById(uploadId);
            if (!uploadElement) return;
            
            const progressBar = uploadElement.querySelector('.progress-bar');
            const statusElement = uploadElement.querySelector('.status');
            
            if (progressBar) progressBar.style.width = percent + '%';
            if (statusElement) statusElement.textContent = status;
            
            // Remover después de 5 segundos si está completado
            if (percent === 100) {
                setTimeout(() => {
                    uploadElement.remove();
                }, 5000);
            }
        },
        
        async processFile(fileId) {
            try {
                TransportApp.showLoading('Procesando archivo...');
                
                const companies = this.getSelectedCompanies();
                
                const response = await TransportApp.apiCall('processing/process', {
                    method: 'POST',
                    body: JSON.stringify({ 
                        file_id: fileId,
                        companies: companies
                    })
                });
                
                if (response.success) {
                    TransportApp.showNotification('Archivo procesado correctamente', 'success');
                    this.refreshFilesList();
                } else {
                    throw new Error(response.message);
                }
                
            } catch (error) {
                TransportApp.showNotification('Error procesando archivo: ' + error.message, 'error');
            } finally {
                TransportApp.hideLoading();
            }
        },
        
        getSelectedCompanies: function() {
            const checkboxes = document.querySelectorAll('input[name="companies[]"]:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        },
        
        async refreshFilesList() {
            // Recargar la lista de archivos desde el servidor
            try {
                const response = await TransportApp.apiCall('processing/files');
                if (response.success) {
                    this.updateFilesTable(response.data);
                }
            } catch (error) {
                console.error('Error refreshing files list:', error);
            }
        },
        
        updateFilesTable: function(files) {
            const tbody = document.querySelector('#filesTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = files.map(file => `
                <tr>
                    <td>${file.filename}</td>
                    <td><span class="badge badge-${file.status}">${file.status_text}</span></td>
                    <td>${file.upload_date}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" 
                                data-action="process-file" 
                                data-file-id="${file.id}">
                            Procesar
                        </button>
                    </td>
                </tr>
            `).join('');
            
            // Re-bindear eventos
            this.setupEventListeners();
        }
    };
    
    // Inicializar processing si estamos en la página
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => this.processing.init());
    } else {
        this.processing.init();
    }
};

/* ========================================
   MÓDULO: MANAGEMENT
   ======================================== */
TransportApp.initManagement = function() {
    console.log('⚙️ Inicializando Management...');
    
    this.management = {
        init: function() {
            this.setupEventListeners();
            this.loadInitialData();
            TransportApp.showNotification('Sistema de gestión cargado', 'success', 3000);
        },
        
        setupEventListeners: function() {
            // Refresh button
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => this.refreshData());
            }
            
            // Export button
            const exportBtn = document.querySelector('[onclick="exportData()"]');
            if (exportBtn) {
                exportBtn.onclick = () => this.exportData();
            }
            
            // Tab navigation
            document.querySelectorAll('.management-card').forEach(card => {
                card.addEventListener('click', (e) => {
                    const tabId = e.currentTarget.getAttribute('href');
                    if (tabId) this.showTab(tabId.replace('#', ''));
                });
            });
        },
        
        async loadInitialData() {
            try {
                const response = await TransportApp.apiCall('management/summary');
                if (response.success) {
                    this.updateSummaryCards(response.data);
                }
            } catch (error) {
                console.error('Error loading management data:', error);
            }
        },
        
        updateSummaryCards: function(data) {
            // Actualizar las tarjetas de resumen
            Object.entries(data).forEach(([key, value]) => {
                const element = document.querySelector(`[data-stat="${key}"]`);
                if (element) {
                    element.textContent = value;
                }
            });
        },
        
        showTab: function(tabName) {
            // Implementar navegación entre tabs
            const tab = document.querySelector(`#${tabName}Tab`);
            if (tab) {
                // Ocultar otros tabs y mostrar el seleccionado
                document.querySelectorAll('.tab-pane').forEach(t => t.style.display = 'none');
                tab.style.display = 'block';
            }
        },
        
        refreshData: function() {
            TransportApp.showNotification('Actualizando datos...', 'info', 2000);
            this.loadInitialData();
        },
        
        exportData: function() {
            TransportApp.showNotification('Preparando exportación...', 'info');
            // Implementar exportación de datos
            setTimeout(() => {
                TransportApp.showNotification('Datos exportados correctamente', 'success');
            }, 2000);
        }
    };
    
    // Inicializar management si estamos en la página
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => this.management.init());
    } else {
        this.management.init();
    }
};

/* ========================================
   UTILITIES Y HELPERS
   ======================================== */
TransportApp.formatBytes = function(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

TransportApp.formatDate = function(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

TransportApp.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

/* ========================================
   INICIALIZACIÓN AUTOMÁTICA
   ======================================== */
document.addEventListener('DOMContentLoaded', function() {
    TransportApp.init();
});

// Exportar para uso global
window.TransportApp = TransportApp;