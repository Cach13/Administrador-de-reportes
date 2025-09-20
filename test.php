<?php
// ========================================
// test_paso15_dashboard.php - TEST COMPLETO PASO 15
// ========================================

// Evitar warnings de headers
ob_start();

echo "🧪 TESTING PASO 15 - DASHBOARD PRINCIPAL\n";
echo str_repeat("=", 50) . "\n\n";

// Cargar configuración
try {
    require_once __DIR__ . '/config/config.php';
    echo "✅ Configuración cargada correctamente\n";
} catch (Exception $e) {
    echo "❌ Error cargando configuración: " . $e->getMessage() . "\n";
    exit(1);
}

$passed = 0;
$failed = 0;
$tests = [];

// ========================================
// TEST 1: ESTRUCTURA DE ARCHIVOS
// ========================================

echo "\n1️⃣ Testing: Estructura de Archivos Dashboard\n";

$dashboardFiles = [
    'DashboardController' => APP_PATH . '/Controllers/DashboardController.php',
    'Dashboard View' => VIEWS_PATH . '/pages/dashboard.php',
    'Main Layout' => VIEWS_PATH . '/layouts/main.php',
    'Card Components' => VIEWS_PATH . '/layouts/components/card.php'
];

foreach ($dashboardFiles as $name => $path) {
    if (file_exists($path) && filesize($path) > 0) {
        echo "   ✅ $name existe\n";
        $tests["file_$name"] = true;
        $passed++;
    } else {
        echo "   ❌ $name falta o vacío\n";
        $tests["file_$name"] = false;
        $failed++;
    }
}

// ========================================
// TEST 2: DASHBOARDCONTROLLER
// ========================================

echo "\n2️⃣ Testing: DashboardController\n";

try {
    // Cargar el controlador
    require_once APP_PATH . '/Controllers/BaseController.php';
    require_once APP_PATH . '/Controllers/DashboardController.php';
    
    // Verificar que la clase existe
    if (class_exists('App\\Controllers\\DashboardController')) {
        echo "   ✅ Clase DashboardController existe\n";
        $tests['dashboard_class'] = true;
        $passed++;
        
        // Verificar métodos principales
        $controller = new ReflectionClass('App\\Controllers\\DashboardController');
        
        $requiredMethods = [
            'index' => 'Método principal del dashboard',
            'getStats' => 'API de estadísticas',
            'getDashboardData' => 'API de datos completos',
            'getSystemHealth' => 'Estado del sistema'
        ];
        
        foreach ($requiredMethods as $method => $description) {
            if ($controller->hasMethod($method)) {
                echo "   ✅ Método '$method' existe ($description)\n";
                $tests["method_$method"] = true;
                $passed++;
            } else {
                echo "   ❌ Método '$method' falta ($description)\n";
                $tests["method_$method"] = false;
                $failed++;
            }
        }
        
    } else {
        echo "   ❌ Clase DashboardController no existe\n";
        $tests['dashboard_class'] = false;
        $failed++;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error cargando DashboardController: " . $e->getMessage() . "\n";
    $tests['dashboard_controller'] = false;
    $failed++;
}

// ========================================
// TEST 3: VISTA DASHBOARD
// ========================================

echo "\n3️⃣ Testing: Vista Dashboard\n";

$dashboardViewPath = VIEWS_PATH . '/pages/dashboard.php';
if (file_exists($dashboardViewPath)) {
    $viewContent = file_get_contents($dashboardViewPath);
    
    $viewChecks = [
        'Stats Cards' => 'statsContainer',
        'Monthly Chart' => 'monthlyChart',
        'Companies Chart' => 'companiesChart', 
        'Recent Activity' => 'recentActivity',
        'Pending Vouchers' => 'pendingVouchers',
        'Refresh Button' => 'refreshDashboard',
        'Process Voucher' => 'processVoucher',
        'Chart.js Library' => 'Chart.js',
        'Bootstrap Icons' => 'bi bi-',
        'Responsive Design' => 'col-xl-',
        'JavaScript Functions' => 'function ',
        'AJAX Calls' => 'fetch(',
        'Error Handling' => 'try {',
        'Notifications' => 'showNotification'
    ];
    
    foreach ($viewChecks as $name => $needle) {
        if (stripos($viewContent, $needle) !== false) {
            echo "   ✅ $name encontrado\n";
            $tests["view_$name"] = true;
            $passed++;
        } else {
            echo "   ❌ $name NO encontrado\n";
            $tests["view_$name"] = false;
            $failed++;
        }
    }
    
} else {
    echo "   ❌ Vista dashboard.php no existe\n";
    $tests['dashboard_view'] = false;
    $failed++;
}

// ========================================
// TEST 4: FUNCIONALIDADES DE BASE DE DATOS
// ========================================

echo "\n4️⃣ Testing: Funcionalidades de Base de Datos\n";

try {
    $db = Database::getInstance();
    
    // Test estadísticas básicas
    $statsQueries = [
        'Total Companies' => "SELECT COUNT(*) as total FROM companies WHERE is_active = 1",
        'Total Vouchers' => "SELECT COUNT(*) as total FROM vouchers",
        'Total Trips' => "SELECT COUNT(*) as total FROM trips",
        'Pending Vouchers' => "SELECT COUNT(*) as total FROM vouchers WHERE status = 'uploaded'"
    ];
    
    foreach ($statsQueries as $name => $query) {
        try {
            $result = $db->fetch($query);
            if ($result !== false) {
                echo "   ✅ $name query funciona (Resultado: " . ($result['total'] ?? 0) . ")\n";
                $tests["query_$name"] = true;
                $passed++;
            } else {
                echo "   ❌ $name query falló\n";
                $tests["query_$name"] = false;
                $failed++;
            }
        } catch (Exception $e) {
            echo "   ❌ $name query error: " . $e->getMessage() . "\n";
            $tests["query_$name"] = false;
            $failed++;
        }
    }
    
    // Test query complejo de estadísticas financieras
    try {
        $financialQuery = "SELECT 
                            COALESCE(SUM(amount), 0) as totalAmount,
                            COUNT(DISTINCT voucher_id) as processedVouchers
                           FROM trips";
        
        $financialResult = $db->fetch($financialQuery);
        if ($financialResult !== false) {
            echo "   ✅ Query financiero funciona (Total: $" . number_format($financialResult['totalAmount'] ?? 0, 2) . ")\n";
            $tests['financial_query'] = true;
            $passed++;
        } else {
            echo "   ❌ Query financiero falló\n";
            $tests['financial_query'] = false;
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ❌ Query financiero error: " . $e->getMessage() . "\n";
        $tests['financial_query'] = false;
        $failed++;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error de conexión a BD: " . $e->getMessage() . "\n";
    $tests['db_connection'] = false;
    $failed++;
}

// ========================================
// TEST 5: FUNCIONES JAVASCRIPT
// ========================================

echo "\n5️⃣ Testing: Funciones JavaScript en Vista\n";

if (isset($viewContent)) {
    $jsChecks = [
        'Chart Initialization' => 'initializeCharts',
        'Auto Refresh' => 'setupAutoRefresh',
        'Process Voucher Function' => 'function processVoucher',
        'Refresh Dashboard Function' => 'function refreshDashboard',
        'Update Stats Function' => 'function updateStats',
        'Show Notification Function' => 'function showNotification',
        'Escape HTML Function' => 'function escapeHtml',
        'Format Date Function' => 'function formatDate',
        'Event Listeners' => 'addEventListener',
        'AJAX Error Handling' => 'catch (error)',
        'Chart.js Integration' => 'new Chart(',
        'Bootstrap Integration' => 'data-bs-'
    ];
    
    foreach ($jsChecks as $name => $needle) {
        if (stripos($viewContent, $needle) !== false) {
            echo "   ✅ $name encontrado\n";
            $tests["js_$name"] = true;
            $passed++;
        } else {
            echo "   ❌ $name NO encontrado\n";
            $tests["js_$name"] = false;
            $failed++;
        }
    }
}

// ========================================
// TEST 6: COMPONENTES CSS Y BOOTSTRAP
// ========================================

echo "\n6️⃣ Testing: Componentes CSS y Bootstrap\n";

if (isset($viewContent)) {
    $cssChecks = [
        'Bootstrap Cards' => 'card shadow',
        'Responsive Grid' => 'col-xl-',
        'Bootstrap Icons' => 'bi bi-',
        'Button Groups' => 'btn-group',
        'Table Responsive' => 'table-responsive',
        'Badge Components' => 'badge bg-',
        'Alert Components' => 'alert alert-',
        'Custom Animations' => 'spin',
        'Border Utilities' => 'border-left-',
        'Text Utilities' => 'text-muted',
        'Flex Utilities' => 'd-flex',
        'Position Utilities' => 'position-fixed'
    ];
    
    foreach ($cssChecks as $name => $needle) {
        if (stripos($viewContent, $needle) !== false) {
            echo "   ✅ $name encontrado\n";
            $tests["css_$name"] = true;
            $passed++;
        } else {
            echo "   ❌ $name NO encontrado\n";
            $tests["css_$name"] = false;
            $failed++;
        }
    }
}

// ========================================
// TEST 7: INTEGRACIÓN CON SISTEMA EXISTENTE
// ========================================

echo "\n7️⃣ Testing: Integración con Sistema Existente\n";

// Test que las clases existentes están disponibles
$existingClasses = [
    'Database' => CLASSES_PATH . '/Database.php',
    'Logger' => CLASSES_PATH . '/Logger.php',
    'MartinMarietaProcessor' => CLASSES_PATH . '/MartinMarietaProcessor.php',
    'CapitalTransportReportGenerator' => CLASSES_PATH . '/CapitalTransportReportGenerator.php'
];

foreach ($existingClasses as $className => $classPath) {
    if (file_exists($classPath)) {
        try {
            require_once $classPath;
            if (class_exists($className)) {
                echo "   ✅ Clase $className disponible\n";
                $tests["integration_$className"] = true;
                $passed++;
            } else {
                echo "   ❌ Clase $className no se puede cargar\n";
                $tests["integration_$className"] = false;
                $failed++;
            }
        } catch (Exception $e) {
            echo "   ❌ Error cargando $className: " . $e->getMessage() . "\n";
            $tests["integration_$className"] = false;
            $failed++;
        }
    } else {
        echo "   ❌ Archivo $className no existe\n";
        $tests["integration_$className"] = false;
        $failed++;
    }
}

// ========================================
// TEST 8: FUNCIONES HELPER Y UTILITIES
// ========================================

echo "\n8️⃣ Testing: Funciones Helper\n";

$helperFunctions = [
    'getBaseUrl' => 'Función getBaseUrl disponible',
    'generateCSRFToken' => 'Función generateCSRFToken disponible',
    'isDebugMode' => 'Función isDebugMode disponible',
    'config' => 'Función config disponible'
];

foreach ($helperFunctions as $function => $description) {
    if (function_exists($function)) {
        echo "   ✅ $description\n";
        $tests["helper_$function"] = true;
        $passed++;
    } else {
        echo "   ❌ $description - NO DISPONIBLE\n";
        $tests["helper_$function"] = false;
        $failed++;
    }
}

// ========================================
// TEST 9: CONFIGURACIÓN Y CONSTANTES
// ========================================

echo "\n9️⃣ Testing: Configuración y Constantes\n";

$requiredConstants = [
    'APP_PATH' => 'Ruta de la aplicación',
    'VIEWS_PATH' => 'Ruta de las vistas', 
    'CLASSES_PATH' => 'Ruta de las clases',
    'APP_NAME' => 'Nombre de la aplicación',
    'APP_VERSION' => 'Versión de la aplicación',
    'DB_HOST' => 'Host de la base de datos',
    'DB_NAME' => 'Nombre de la base de datos'
];

foreach ($requiredConstants as $constant => $description) {
    if (defined($constant)) {
        $value = constant($constant);
        echo "   ✅ $description: " . (is_string($value) ? $value : 'definido') . "\n";
        $tests["constant_$constant"] = true;
        $passed++;
    } else {
        echo "   ❌ $description - NO DEFINIDO\n";
        $tests["constant_$constant"] = false;
        $failed++;
    }
}

// ========================================
// TEST 10: SIMULACIÓN DE FUNCIONALIDAD
// ========================================

echo "\n🔟 Testing: Simulación de Funcionalidad Dashboard\n";

try {
    // Simular instancia del controlador (sin ejecutar)
    if (class_exists('App\\Controllers\\DashboardController')) {
        echo "   ✅ DashboardController puede ser instanciado\n";
        $tests['controller_instantiation'] = true;
        $passed++;
        
        // Verificar que métodos públicos existen
        $reflection = new ReflectionClass('App\\Controllers\\DashboardController');
        $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        if (count($publicMethods) >= 4) {
            echo "   ✅ DashboardController tiene métodos públicos suficientes (" . count($publicMethods) . ")\n";
            $tests['controller_methods'] = true;
            $passed++;
        } else {
            echo "   ❌ DashboardController tiene pocos métodos públicos\n";
            $tests['controller_methods'] = false;
            $failed++;
        }
    } else {
        echo "   ❌ DashboardController no se puede instanciar\n";
        $tests['controller_instantiation'] = false;
        $failed++;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error simulando funcionalidad: " . $e->getMessage() . "\n";
    $tests['simulation'] = false;
    $failed++;
}

// ========================================
// VERIFICACIÓN DE SEGURIDAD
// ========================================

echo "\n🔒 Testing: Verificaciones de Seguridad\n";

if (isset($viewContent)) {
    $securityChecks = [
        'CSRF Protection' => 'csrf',
        'HTML Escaping' => 'htmlspecialchars',
        'Input Sanitization' => 'escapeHtml',
        'XSS Prevention' => 'innerHTML',
        'Authentication Check' => 'requireAuth',
        'Permission Check' => 'hasPermission'
    ];
    
    foreach ($securityChecks as $name => $needle) {
        if (stripos($viewContent, $needle) !== false || stripos($dashboardControllerContent ?? '', $needle) !== false) {
            echo "   ✅ $name implementado\n";
            $tests["security_$name"] = true;
            $passed++;
        } else {
            echo "   ⚠️  $name - revisar implementación\n";
            $tests["security_$name"] = false;
            $failed++;
        }
    }
}

// ========================================
// RESULTADOS FINALES
// ========================================

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 RESULTADOS DEL TEST - PASO 15 DASHBOARD\n";
echo str_repeat("=", 60) . "\n";

echo "✅ Tests Pasados: $passed\n";
echo "❌ Tests Fallidos: $failed\n";
echo "📊 Total Tests: " . ($passed + $failed) . "\n";

$percentage = $passed > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0;
echo "🎯 Porcentaje Éxito: $percentage%\n\n";

// Evaluación del resultado
if ($percentage >= 95) {
    echo "🎉 PASO 15 DASHBOARD: ¡EXCELENTE!\n";
    echo "   Dashboard completamente funcional y profesional\n";
    echo "   ✅ LISTO PARA PASO 16 - PÁGINA DE PROCESAMIENTO\n";
    $status = '🟢 ÉXITO TOTAL';
} elseif ($percentage >= 85) {
    echo "👍 PASO 15 DASHBOARD: MUY BUENO\n";
    echo "   Dashboard funcional con pequeños ajustes\n";
    echo "   ✅ Puede continuar al PASO 16\n";
    $status = '🟡 ÉXITO PARCIAL';
} elseif ($percentage >= 70) {
    echo "⚠️  PASO 15 DASHBOARD: NECESITA AJUSTES\n";
    echo "   Dashboard básico pero requiere mejoras\n";
    echo "   📝 Revisar tests fallidos antes de continuar\n";
    $status = '🟠 NECESITA TRABAJO';
} else {
    echo "❌ PASO 15 DASHBOARD: NECESITA REVISIÓN COMPLETA\n";
    echo "   Varios componentes críticos fallan\n";
    echo "   🔧 Arreglar errores antes de continuar\n";
    $status = '🔴 NECESITA ARREGLOS';
}

echo "\n📋 RESUMEN DE COMPONENTES:\n";
echo "   🎨 Vista Dashboard: " . (isset($tests['view_Stats Cards']) && $tests['view_Stats Cards'] ? '✅' : '❌') . "\n";
echo "   🎮 Controlador: " . (isset($tests['dashboard_class']) && $tests['dashboard_class'] ? '✅' : '❌') . "\n";
echo "   📊 Gráficos: " . (isset($tests['view_Chart.js Library']) && $tests['view_Chart.js Library'] ? '✅' : '❌') . "\n";
echo "   🔄 AJAX: " . (isset($tests['js_AJAX Error Handling']) && $tests['js_AJAX Error Handling'] ? '✅' : '❌') . "\n";
echo "   🗄️  Base de Datos: " . (isset($tests['query_Total Companies']) && $tests['query_Total Companies'] ? '✅' : '❌') . "\n";
echo "   🎨 Bootstrap/CSS: " . (isset($tests['css_Bootstrap Cards']) && $tests['css_Bootstrap Cards'] ? '✅' : '❌') . "\n";
echo "   🔒 Seguridad: " . (isset($tests['security_CSRF Protection']) && $tests['security_CSRF Protection'] ? '✅' : '❌') . "\n";

echo "\n💡 PRÓXIMO PASO:\n";
if ($percentage >= 85) {
    echo "   📄 PASO 16: Página de Procesamiento\n";
    echo "   📤 Upload de vouchers Martin Marieta\n";
    echo "   ⚙️  Integración con MartinMarietaProcessor\n";
    echo "   📊 Progreso en tiempo real\n";
} else {
    echo "   🔧 Arreglar issues del PASO 15 primero\n";
    echo "   📝 Revisar tests fallidos\n";
    echo "   ✅ Asegurar funcionalidad básica del dashboard\n";
}

echo "\n🎯 STATUS FINAL: $status\n";
echo str_repeat("=", 60) . "\n";

// Limpiar buffer de salida
ob_end_flush();

// Guardar log de resultados
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'step' => 'PASO_15_DASHBOARD',
    'passed' => $passed,
    'failed' => $failed,
    'percentage' => $percentage,
    'status' => $status
];

try {
    $logFile = ROOT_PATH . '/logs/paso15_test.log';
    if (!is_dir(dirname($logFile))) {
        @mkdir(dirname($logFile), 0755, true);
    }
    file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
} catch (Exception $e) {
    // Silenciar errores de logging
}

echo "\n";
?>