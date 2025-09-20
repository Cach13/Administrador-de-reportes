/**
 * Upload Handler - JavaScript para manejo de subida multi-formato
 * Ruta: /assets/js/upload-handler.js
 */

class UploadHandler {
    constructor(options = {}) {
        this.options = {
            uploadUrl: '/api/upload-file.php',
            processUrl: '/api/process-file.php',
            statusUrl: '/api/file-status.php',
            maxFileSize: 20 * 1024 * 1024, // 20MB por defecto
            allowedTypes: {
                pdf: {
                    extensions: ['pdf'],
                    mimeTypes: ['application/pdf'],
                    icon: 'fas fa-file-pdf',
                    color: '#dc2626',
                    maxSize: 20 * 1024 * 1024
                },
                excel: {
                    extensions: ['xlsx', 'xls'],
                    mimeTypes: [
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel'
                    ],
                    icon: 'fas fa-file-excel',
                    color: '#10b981',
                    maxSize: 10 * 1024 * 1024
                }
            },
            autoProcess: false,
            ...options
        };
        
        this.initializeElements();
        this.bindEvents();
        this.uploads = new Map(); // Track de uploads activos
    }
    
    initializeElements() {
        this.uploadArea = document.getElementById('uploadArea');
        this.fileInput = document.getElementById('fileInput');
        this.progressContainer = document.getElementById('progressContainer');
        this.progressFill = document.getElementById('progressFill');
        this.progressIcon = document.getElementById('progressIcon');
        this.progressFileName = document.getElementById('progressFileName');
        this.progressPercentage = document.getElementById('progressPercentage');
        this.progressStatus = document.getElementById('progressStatus');
        this.progressSize = document.getElementById('progressSize');
        
        if (!this.uploadArea || !this.fileInput) {
            console.error('UploadHandler: Elementos requeridos no encontrados');
            return;
        }
    }
    
    bindEvents() {
        // Drag and drop
        this.uploadArea.addEventListener('dragover', (e) => this.handleDragOver(e));
        this.uploadArea.addEventListener('dragleave', (e) => this.handleDragLeave(e));
        this.uploadArea.addEventListener('drop', (e) => this.handleDrop(e));
        
        // Click para seleccionar
        this.uploadArea.addEventListener('click', () => this.fileInput.click());
        
        // Input change
        this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
    }
    
    handleDragOver(e) {
        e.preventDefault();
        this.uploadArea.classList.add('dragover');
    }
    
    handleDragLeave(e) {
        e.preventDefault();
        this.uploadArea.classList.remove('dragover');
    }
    
    handleDrop(e) {
        e.preventDefault();
        this.uploadArea.classList.remove('dragover');
        
        const files = Array.from(e.dataTransfer.files);
        this.processFiles(files);
    }
    
    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        this.processFiles(files);
    }
    
    processFiles(files) {
        for (const file of files) {
            this.uploadFile(file);
        }
        
        // Limpiar input
        if (this.fileInput) {
            this.fileInput.value = '';
        }
    }
    
    validateFile(file) {
        const errors = [];
        const warnings = [];
        
        // Verificar tamaño
        if (file.size > this.options.maxFileSize) {
            errors.push(`Archivo excede el tamaño máximo (${this.formatFileSize(this.options.maxFileSize)})`);
        }
        
        // Detectar tipo de archivo
        const fileType = this.detectFileType(file);
        
        if (!fileType) {
            errors.push('Tipo de archivo no soportado');
            return { valid: false, errors, warnings, fileType: null };
        }
        
        // Verificar límites específicos del tipo
        const typeConfig = this.options.allowedTypes[fileType];
        if (file.size > typeConfig.maxSize) {
            errors.push(`Archivo ${fileType.toUpperCase()} excede el tamaño máximo (${this.formatFileSize(typeConfig.maxSize)})`);
        }
        
        // Advertencias
        if (file.size > 5 * 1024 * 1024) { // 5MB
            warnings.push('Archivo grande, el procesamiento puede tardar más');
        }
        
        return {
            valid: errors.length === 0,
            errors,
            warnings,
            fileType
        };
    }
    
    detectFileType(file) {
        const extension = file.name.split('.').pop().toLowerCase();
        const mimeType = file.type;
        
        for (const [type, config] of Object.entries(this.options.allowedTypes)) {
            if (config.extensions.includes(extension) || config.mimeTypes.includes(mimeType)) {
                return type;
            }
        }
        
        return null;
    }
    
    async uploadFile(file) {
        const uploadId = this.generateUploadId();
        
        // Validar archivo
        const validation = this.validateFile(file);
        
        if (!validation.valid) {
            this.showError(validation.errors.join(', '));
            return;
        }
        
        // Mostrar advertencias si las hay
        if (validation.warnings.length > 0) {
            console.warn('Upload warnings:', validation.warnings);
        }
        
        // Configurar upload
        const upload = {
            id: uploadId,
            file: file,
            fileType: validation.fileType,
            status: 'uploading',
            progress: 0,
            xhr: null
        };
        
        this.uploads.set(uploadId, upload);
        
        try {
            // Mostrar progreso
            this.showProgress(upload);
            
            // Crear FormData
            const formData = new FormData();
            formData.append('file', file);
            
            // Configurar XMLHttpRequest
            const xhr = new XMLHttpRequest();
            upload.xhr = xhr;
            
            // Progress handler
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const progress = (e.loaded / e.total) * 100;
                    this.updateProgress(uploadId, progress, 'Subiendo...');
                }
            });
            
            // Response handler
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        this.handleUploadSuccess(uploadId, response);
                    } catch (e) {
                        this.handleUploadError(uploadId, 'Error procesando respuesta del servidor');
                    }
                } else {
                    try {
                        const error = JSON.parse(xhr.responseText);
                        this.handleUploadError(uploadId, error.error || 'Error en el servidor');
                    } catch (e) {
                        this.handleUploadError(uploadId, `Error del servidor (${xhr.status})`);
                    }
                }
            });
            
            // Error handler
            xhr.addEventListener('error', () => {
                this.handleUploadError(uploadId, 'Error de conexión');
            });
            
            // Abort handler
            xhr.addEventListener('abort', () => {
                this.handleUploadError(uploadId, 'Upload cancelado');
            });
            
            // Enviar request
            xhr.open('POST', this.options.uploadUrl);
            xhr.send(formData);
            
        } catch (error) {
            this.handleUploadError(uploadId, error.message);
        }
    }
    
    showProgress(upload) {
        if (!this.progressContainer) return;
        
        const typeConfig = this.options.allowedTypes[upload.fileType];
        
        // Actualizar icono según tipo
        if (this.progressIcon) {
            this.progressIcon.innerHTML = `<i class="${typeConfig.icon}" style="color: ${typeConfig.color};"></i>`;
        }
        
        // Actualizar información
        if (this.progressFileName) {
            this.progressFileName.textContent = upload.file.name;
        }
        
        if (this.progressSize) {
            this.progressSize.textContent = this.formatFileSize(upload.file.size);
        }
        
        // Mostrar contenedor
        this.progressContainer.style.display = 'block';
        
        // Actualizar progreso inicial
        this.updateProgress(upload.id, 0, 'Preparando...');
    }
    
    updateProgress(uploadId, progress, status) {
        const upload = this.uploads.get(uploadId);
        if (!upload) return;
        
        upload.progress = progress;
        upload.status = status;
        
        // Actualizar UI
        if (this.progressFill) {
            this.progressFill.style.width = `${progress}%`;
        }
        
        if (this.progressPercentage) {
            this.progressPercentage.textContent = `${Math.round(progress)}%`;
        }
        
        if (this.progressStatus) {
            this.progressStatus.textContent = status;
        }
    }
    
    handleUploadSuccess(uploadId, response) {
        const upload = this.uploads.get(uploadId);
        if (!upload) return;
        
        upload.status = 'completed';
        upload.voucherId = response.data.voucher_id;
        
        this.updateProgress(uploadId, 100, 'Upload completado');
        
        // Mostrar información del archivo
        this.showUploadResult(response);
        
        // Auto-procesar si está habilitado
        if (this.options.autoProcess) {
            setTimeout(() => {
                this.processFile(response.data.voucher_id);
            }, 1000);
        } else {
            // Ocultar progreso después de un delay
            setTimeout(() => {
                this.hideProgress();
                this.showSuccessMessage(`${upload.file.name} subido exitosamente`);
            }, 2000);
        }
        
        // Disparar evento personalizado
        this.dispatchUploadEvent('upload-success', {
            uploadId,
            upload,
            response
        });
    }
    
    handleUploadError(uploadId, errorMessage) {
        const upload = this.uploads.get(uploadId);
        if (!upload) return;
        
        upload.status = 'error';
        upload.error = errorMessage;
        
        this.updateProgress(uploadId, 0, 'Error');
        this.showError(`Error subiendo ${upload.file.name}: ${errorMessage}`);
        
        // Ocultar progreso
        setTimeout(() => {
            this.hideProgress();
        }, 3000);
        
        // Disparar evento de error
        this.dispatchUploadEvent('upload-error', {
            uploadId,
            upload,
            error: errorMessage
        });
        
        // Limpiar upload fallido
        this.uploads.delete(uploadId);
    }
    
    async processFile(voucherId) {
        try {
            this.updateProgress(null, 0, 'Procesando archivo...');
            
            const response = await fetch(this.options.processUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ voucher_id: voucherId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updateProgress(null, 100, 'Procesamiento completado');
                this.showProcessingResult(result);
                
                setTimeout(() => {
                    this.hideProgress();
                    this.showSuccessMessage('Archivo procesado exitosamente');
                    
                    // Recargar tabla o actualizar UI
                    if (typeof window.refreshFileTable === 'function') {
                        window.refreshFileTable();
                    } else {
                        location.reload();
                    }
                }, 2000);
                
            } else {
                throw new Error(result.error || 'Error en procesamiento');
            }
            
        } catch (error) {
            this.updateProgress(null, 0, 'Error en procesamiento');
            this.showError(`Error procesando archivo: ${error.message}`);
            
            setTimeout(() => {
                this.hideProgress();
            }, 3000);
        }
    }
    
    showUploadResult(response) {
        console.log('Upload result:', response);
        
        // Mostrar warnings si los hay
        if (response.warnings && response.warnings.length > 0) {
            response.warnings.forEach(warning => {
                this.showWarning(warning);
            });
        }
    }
    
    showProcessingResult(result) {
        console.log('Processing result:', result);
        
        if (result.statistics) {
            const stats = result.statistics;
            const message = `Procesados ${stats.total_trips} viajes de ${stats.total_companies} empresas`;
            this.showInfo(message);
        }
    }
    
    hideProgress() {
        if (this.progressContainer) {
            this.progressContainer.style.display = 'none';
        }
        
        // Reset progress
        if (this.progressFill) {
            this.progressFill.style.width = '0%';
        }
    }
    
    // Métodos de notificación
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    showWarning(message) {
        this.showNotification(message, 'warning');
    }
    
    showInfo(message) {
        this.showNotification(message, 'info');
    }
    
    showSuccessMessage(message) {
        this.showNotification(message, 'success');
    }
    
    showNotification(message, type = 'info') {
        // Implementación básica - puede ser reemplazada por sistema de notificaciones más avanzado
        const className = {
            error: 'alert-danger',
            warning: 'alert-warning',
            info: 'alert-info',
            success: 'alert-success'
        }[type] || 'alert-info';
        
        const alert = document.createElement('div');
        alert.className = `alert ${className} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Buscar contenedor de alertas o crear uno
        let alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alert-container';
            alertContainer.style.position = 'fixed';
            alertContainer.style.top = '20px';
            alertContainer.style.right = '20px';
            alertContainer.style.zIndex = '9999';
            alertContainer.style.maxWidth = '400px';
            document.body.appendChild(alertContainer);
        }
        
        alertContainer.appendChild(alert);
        
        // Auto-remove después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 5000);
    }
    
    // Utilidades
    generateUploadId() {
        return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    dispatchUploadEvent(eventName, detail) {
        const event = new CustomEvent(eventName, {
            detail: detail,
            bubbles: true
        });
        
        if (this.uploadArea) {
            this.uploadArea.dispatchEvent(event);
        } else {
            document.dispatchEvent(event);
        }
    }
    
    // Métodos públicos para control externo
    cancelUpload(uploadId) {
        const upload = this.uploads.get(uploadId);
        if (upload && upload.xhr) {
            upload.xhr.abort();
            this.uploads.delete(uploadId);
        }
    }
    
    cancelAllUploads() {
        for (const [uploadId] of this.uploads) {
            this.cancelUpload(uploadId);
        }
    }
    
    getActiveUploads() {
        return Array.from(this.uploads.values());
    }
    
    setAutoProcess(enabled) {
        this.options.autoProcess = enabled;
    }
}

// Función de conveniencia para inicializar
window.initializeUploadHandler = function(options = {}) {
    return new UploadHandler(options);
};

// Auto-inicializar SOLO UNA VEZ
document.addEventListener('DOMContentLoaded', function() {
    // Evitar inicialización múltiple
    if (window.uploadHandlerInitialized) {
        console.log('UploadHandler ya inicializado, saltando...');
        return;
    }
    
    if (document.getElementById('uploadArea') && document.getElementById('fileInput')) {
        window.uploadHandler = new UploadHandler({
            autoProcess: false // Cambiar a true para procesamiento automático
        });
        
        window.uploadHandlerInitialized = true;
        console.log('UploadHandler inicializado correctamente');
    }
});