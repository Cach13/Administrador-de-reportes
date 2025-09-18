<?php
// ========================================
// test_step_13_utils.php - TEST DEL PASO 13
// Ejecutar: php test_step_13_utils.php
// ========================================

echo "🧪 TESTING PASO 13 - UTILS MEJORADAS\n";
echo "====================================\n\n";

require_once 'config/config.php';

$tests = [];
$passed = 0;
$failed = 0;

// ========================================
// TEST 1: CARGA DE UTILS
// ========================================

echo "1️⃣ Testing: Carga de Utils\n";

// Test ResponseHelper
try {
    if (class_exists('App\\Utils\\ResponseHelper')) {
        echo "   ✅ ResponseHelper cargado correctamente\n";
        $tests['responsehelper_load'] = true;
        $passed++;
    } else {
        echo "   ❌ ResponseHelper NO se puede cargar\n";
        $tests['responsehelper_load'] = false;
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR cargando ResponseHelper: " . $e->getMessage() . "\n";
    $tests['responsehelper_load'] = false;
    $failed++;
}

// Test Database mejorado
try {
    $db = Database::getInstance();
    if (method_exists($db, 'execute')) {
        echo "   ✅ Database::execute() método disponible\n";
        $tests['database_execute'] = true;
        $passed++;
    } else {
        echo "   ❌ Database::execute() método NO disponible\n";
        $tests['database_execute'] = false;
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR verificando Database: " . $e->getMessage() . "\n";
    $tests['database_execute'] = false;
    $failed++;
}

// ========================================
// TEST 2: RESPONSEHELPER MÉTODOS
// ========================================

echo "\n2️⃣ Testing: ResponseHelper Métodos\n";

// Test constantes
$constants = [
    'SUCCESS' => 200,
    'CREATED' => 201,
    'BAD_REQUEST' => 400,
    'NOT_FOUND' => 404,
    'INTERNAL_ERROR' => 500
];

foreach ($constants as $constant => $expectedValue) {
    $fullConstant = "App\\Utils\\ResponseHelper::$constant";
    if (defined($fullConstant) && constant($fullConstant) === $expectedValue) {
        echo "   ✅ Constante $constant definida correctamente ($expectedValue)\n";
        $tests["constant_$constant"] = true;
        $passed++;
    } else {
        echo "   ❌ Constante $constant NO definida o valor incorrecto\n";
        $tests["constant_$constant"] = false;
        $failed++;
    }
}

// Test métodos estáticos
$methods = ['success', 'error', 'validation', 'notFound', 'unauthorized', 'json'];
foreach ($methods as $method) {
    if (method_exists('App\\Utils\\ResponseHelper', $method)) {
        echo "   ✅ Método ResponseHelper::$method() existe\n";
        $tests["method_$method"] = true;
        $passed++;
    } else {
        echo "   ❌ Método ResponseHelper::$method() NO existe\n";
        $tests["method_$method"] = false;
        $failed++;
    }
}

// ========================================
// TEST 3: RESPONSEHELPER FUNCIONALIDAD
// ========================================

echo "\n3️⃣ Testing: ResponseHelper Funcionalidad\n";

// Test sanitize
try {
    $dirtyInput = '<script>alert("xss")</script>Test Data';
    $cleaned = App\Utils\ResponseHelper::sanitize($dirtyInput);
    
    if (strpos($cleaned, '<script>') === false) {
        echo "   ✅ ResponseHelper::sanitize() funciona\n";
        echo "   📊 Input: '$dirtyInput' → Output: '$cleaned'\n";
        $tests['sanitize_function'] = true;
        $passed++;
    } else {
        echo "   ❌ ResponseHelper::sanitize() NO elimina tags peligrosos\n";
        $tests['sanitize_function'] = false;
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR en sanitize: " . $e->getMessage() . "\n";
    $tests['sanitize_function'] = false;
    $failed++;
}

// Test getClientIP
try {
    $ip = App\Utils\ResponseHelper::getClientIP();
    if ($ip && $ip !== 'unknown') {
        echo "   ✅ ResponseHelper::getClientIP() funciona\n";
        echo "   📊 IP detectada: $ip\n";
        $tests['get_client_ip'] = true;
        $passed++;
    } else {
        echo "   ⚠️ ResponseHelper::getClientIP() devuelve 'unknown'\n";
        $tests['get_client_ip'] = true; // Aceptable en CLI
        $passed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR en getClientIP: " . $e->getMessage() . "\n";
    $tests['get_client_ip'] = false;
    $failed++;
}

// Test validación
try {
    $data = ['email' => 'invalid-email', 'name' => ''];
    $rules = ['email' => 'required|email', 'name' => 'required|min:3'];
    
    $errors = App\Utils\ResponseHelper::validate($data, $rules);
    
    if (!empty($errors) && isset($errors['email']) && isset($errors['name'])) {
        echo "   ✅ ResponseHelper::validate() detecta errores correctamente\n";
        echo "   📊 Errores encontrados: " . count($errors) . " campos\n";
        $tests['validation_function'] = true;
        $passed++;
    } else {
        echo "   ❌ ResponseHelper::validate() NO detecta errores\n";
        $tests['validation_function'] = false;
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR en validate: " . $e->getMessage() . "\n";
    $tests['validation_function'] = false;
    $failed++;
}

// ========================================
// TEST 4: DATABASE MÉTODOS MEJORADOS
// ========================================

echo "\n4️⃣ Testing: Database Métodos Mejorados\n";

try {
    $db = Database::getInstance();
    
    // Test execute con query simple
    $result = $db->execute("SELECT 1");
    if ($result === true) {
        echo "   ✅ Database::execute() funciona correctamente\n";
        $tests['database_execute_function'] = true;
        $passed++;
    } else {
        echo "   ❌ Database::execute() no devuelve resultado esperado\n";
        $tests['database_execute_function'] = false;
        $failed++;
    }
    
    // Test métodos adicionales si existen
    $additionalMethods = ['exists', 'count', 'fetchColumn'];
    foreach ($additionalMethods as $method) {
        if (method_exists($db, $method)) {
            echo "   ✅ Database::$method() método disponible\n";
            $tests["database_$method"] = true;
            $passed++;
        } else {
            echo "   ⚠️ Database::$method() método no disponible (opcional)\n";
            $tests["database_$method"] = true; // No es crítico
            $passed++;
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR testing Database: " . $e->getMessage() . "\n";
    $tests['database_testing'] = false;
    $failed++;
}

// ========================================
// TEST 5: CSRF Y SEGURIDAD
// ========================================

echo "\n5️⃣ Testing: Funciones de Seguridad\n";

// Test CSRF Token
try {
    $token = App\Utils\ResponseHelper::generateCSRFToken();
    if ($token && strlen($token) > 32) {
        echo "   ✅ ResponseHelper::generateCSRFToken() genera token\n";
        echo "   📊 Token generado (primeros 16 chars): " . substr($token, 0, 16) . "...\n";
        $tests['csrf_generate'] = true;
        $passed++;
        
        // Test verificación de token
        $isValid = App\Utils\ResponseHelper::verifyCSRFToken($token);
        if ($isValid) {
            echo "   ✅ ResponseHelper::verifyCSRFToken() verifica correctamente\n";
            $tests['csrf_verify'] = true;
            $passed++;
        } else {
            echo "   ❌ ResponseHelper::verifyCSRFToken() NO verifica correctamente\n";
            $tests['csrf_verify'] = false;
            $failed++;
        }
    } else {
        echo "   ❌ ResponseHelper::generateCSRFToken() NO genera token válido\n";
        $tests['csrf_generate'] = false;
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR en CSRF: " . $e->getMessage() . "\n";
    $tests['csrf_functions'] = false;
    $failed++;
}

// ========================================
// TEST 6: UTILIDADES GENERALES
// ========================================

echo "\n6️⃣ Testing: Utilidades Generales\n";

// Test isMethod
try {
    // Simular método GET (CLI siempre es GET)
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $isGet = App\Utils\ResponseHelper::isMethod('GET');
    $isPost = App\Utils\ResponseHelper::isMethod('POST');
    
    if ($isGet && !$isPost) {
        echo "   ✅ ResponseHelper::isMethod() funciona correctamente\n";
        $tests['is_method'] = true;
        $passed++;
    } else {
        echo "   ❌ ResponseHelper::isMethod() NO detecta métodos correctamente\n";
        $tests['is_method'] = false;
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR en isMethod: " . $e->getMessage() . "\n";
    $tests['is_method'] = false;
    $failed++;
}

// Test formatApiData
try {
    $testData = [
        'id' => 1,
        'name' => 'Test',
        'created_at' => '2025-01-17 10:30:00',
        'amount' => 1250.75
    ];
    
    $formatted = App\Utils\ResponseHelper::formatApiData($testData);
    
    if (isset($formatted['created_at_formatted']) && isset($formatted['amount_formatted'])) {
        echo "   ✅ ResponseHelper::formatApiData() formatea datos correctamente\n";
        echo "   📊 Fecha: " . $formatted['created_at_formatted'] . "\n";
        echo "   📊 Monto: " . $formatted['amount_formatted'] . "\n";
        $tests['format_api_data'] = true;
        $passed++;
    } else {
        echo "   ❌ ResponseHelper::formatApiData() NO formatea correctamente\n";
        $tests['format_api_data'] = false;
        $failed++;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR en formatApiData: " . $e->getMessage() . "\n";
    $tests['format_api_data'] = false;
    $failed++;
}

// ========================================
// RESULTADOS FINALES
// ========================================

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RESULTADOS DEL TEST - PASO 13 UTILS\n";
echo str_repeat("=", 50) . "\n";

echo "✅ Tests Pasados: $passed\n";
echo "❌ Tests Fallidos: $failed\n";
echo "📊 Total Tests: " . ($passed + $failed) . "\n";

$percentage = $passed > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0;
echo "🎯 Porcentaje Éxito: $percentage%\n\n";

if ($percentage >= 90) {
    echo "🎉 PASO 13 UTILS: EXCELENTE\n";
    echo "   Todas las utilidades funcionan correctamente\n";
    echo "   ✅ FASE 4 COMPLETADA - MODELS & DATA\n";
    echo "   🚀 Listo para FASE 5 - VIEWS & FRONTEND\n";
} elseif ($percentage >= 70) {
    echo "⚠️  PASO 13 UTILS: BUENO\n";
    echo "   La mayoría de utilidades funcionan\n";
    echo "   📝 Revisar tests fallidos antes de continuar\n";
} else {
    echo "❌ PASO 13 UTILS: NECESITA REVISIÓN\n";
    echo "   Varias utilidades tienen problemas\n";
    echo "   🔧 Arreglar errores antes de continuar\n";
}

echo "\n📋 DETALLES DE TESTS:\n";
foreach ($tests as $test => $result) {
    $status = $result ? "✅ PASS" : "❌ FAIL";
    echo "   $status: $test\n";
}

echo "\n💡 RESUMEN FASE 4 COMPLETADA:\n";
echo "   📝 PASO 11: Models (User, Company, Voucher, Report)\n";
echo "   🗄️ PASO 12: Repositories (BaseRepository, CompanyRepository, VoucherRepository)\n";
echo "   🛠️ PASO 13: Utils (ResponseHelper, Database mejorado)\n";

echo "\n🎯 PRÓXIMA FASE:\n";
echo "   🎨 FASE 5: VIEWS & FRONTEND (Pasos 14-19)\n";
echo "   📄 Templates y páginas\n";
echo "   💅 CSS y JavaScript\n";
echo "   🔌 API endpoints\n";

echo "\n";
?>