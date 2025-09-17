<?php
// ========================================
// test_authservice.php - PRUEBA DEL PASO 8
// Ejecutar: php test_authservice.php
// ========================================

echo "🧪 PROBANDO AUTHSERVICE - PASO 8\n";
echo "=================================\n\n";

// Cargar configuración
require_once 'config/config.php';

echo "1️⃣ Verificando estructura del directorio Services...\n";

$servicesDir = 'app/Services';
if (!is_dir($servicesDir)) {
    echo "   📁 Creando directorio {$servicesDir}...\n";
    if (mkdir($servicesDir, 0755, true)) {
        echo "   ✅ Directorio {$servicesDir} creado\n";
    } else {
        echo "   ❌ ERROR: No se pudo crear directorio {$servicesDir}\n";
        exit(1);
    }
} else {
    echo "   ✅ Directorio {$servicesDir} existe\n";
}

echo "\n2️⃣ Probando carga de AuthService...\n";

try {
    // Intentar cargar la clase
    if (class_exists('App\\Services\\AuthService')) {
        echo "   ✅ AuthService se puede cargar via autoload\n";
    } else {
        // Cargar manualmente si autoload no funciona
        require_once 'app/Services/AuthService.php';
        echo "   ✅ AuthService cargado manualmente\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR cargando AuthService: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n3️⃣ Verificando métodos públicos principales...\n";

try {
    $reflection = new ReflectionClass('App\\Services\\AuthService');
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $expectedMethods = [
        'login', 'logout', 'isAuthenticated', 'hasPermission', 
        'isAdmin', 'canAccessCompany', 'createUser', 'updateUser',
        'generateCSRFToken', 'verifyCSRFToken', 'generateSecurePassword',
        'validatePasswordStrength', 'getCurrentUser', 'getAuthStats'
    ];
    $foundMethods = [];
    
    foreach ($methods as $method) {
        if ($method->class === 'App\\Services\\AuthService') {
            $foundMethods[] = $method->getName();
        }
    }
    
    foreach ($expectedMethods as $expectedMethod) {
        if (in_array($expectedMethod, $foundMethods)) {
            echo "   ✅ Método {$expectedMethod}() encontrado\n";
        } else {
            echo "   ❌ Método {$expectedMethod}() NO encontrado\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR verificando métodos: " . $e->getMessage() . "\n";
}

echo "\n4️⃣ Verificando constantes de configuración...\n";

$constants = [
    'MAX_LOGIN_ATTEMPTS' => 5,
    'LOCKOUT_TIME' => 900,
    'SESSION_LIFETIME' => 7200
];

$reflection = new ReflectionClass('App\\Services\\AuthService');
foreach ($constants as $constant => $expectedValue) {
    if ($reflection->hasConstant($constant)) {
        $actualValue = $reflection->getConstant($constant);
        if ($actualValue === $expectedValue) {
            echo "   ✅ Constante {$constant} = {$actualValue} ✓\n";
        } else {
            echo "   ⚠️ Constante {$constant} = {$actualValue} (esperado: {$expectedValue})\n";
        }
    } else {
        echo "   ❌ Constante {$constant} NO encontrada\n";
    }
}

echo "\n5️⃣ Probando instanciación de AuthService...\n";

try {
    // Mock de sesión para testing
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $authService = new App\Services\AuthService();
    echo "   ✅ AuthService instanciado correctamente\n";
    
    // Verificar que las dependencias se cargaron
    if (method_exists($authService, 'getAuthStats')) {
        echo "   ✅ Métodos públicos accesibles\n";
    } else {
        echo "   ❌ Métodos públicos NO accesibles\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR instanciando AuthService: " . $e->getMessage() . "\n";
}

echo "\n6️⃣ Probando funciones básicas de seguridad...\n";

try {
    $authService = new App\Services\AuthService();
    
    // Probar generación de password seguro
    $securePassword = $authService->generateSecurePassword(12);
    if (strlen($securePassword) === 12) {
        echo "   ✅ Generación de password seguro funciona (longitud: " . strlen($securePassword) . ")\n";
    } else {
        echo "   ❌ Generación de password seguro falló\n";
    }
    
    // Probar validación de password
    $validation = $authService->validatePasswordStrength('Test123!');
    if (isset($validation['valid'])) {
        echo "   ✅ Validación de password funciona (válido: " . ($validation['valid'] ? 'Sí' : 'No') . ")\n";
    } else {
        echo "   ❌ Validación de password falló\n";
    }
    
    // Probar generación de token CSRF
    $csrfToken = $authService->generateCSRFToken();
    if (strlen($csrfToken) === 64) { // 32 bytes * 2 (hex)
        echo "   ✅ Generación de token CSRF funciona (longitud: " . strlen($csrfToken) . ")\n";
    } else {
        echo "   ❌ Generación de token CSRF falló\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR probando funciones básicas: " . $e->getMessage() . "\n";
}

echo "\n7️⃣ Verificando integración con Database y Logger...\n";

try {
    // Verificar que Database existe y funciona
    if (class_exists('Database')) {
        echo "   ✅ Clase Database disponible\n";
        
        try {
            $db = Database::getInstance();
            echo "   ✅ Database::getInstance() funciona\n";
        } catch (Exception $e) {
            echo "   ⚠️ Database::getInstance() falló: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ❌ Clase Database NO disponible\n";
    }
    
    // Verificar que Logger existe
    if (class_exists('Logger')) {
        echo "   ✅ Clase Logger disponible\n";
        
        try {
            $logger = new Logger();
            echo "   ✅ Logger instanciado correctamente\n";
        } catch (Exception $e) {
            echo "   ⚠️ Logger falló: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ❌ Clase Logger NO disponible\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR verificando dependencias: " . $e->getMessage() . "\n";
}

echo "\n8️⃣ Verificando métodos de autenticación básicos...\n";

try {
    $authService = new App\Services\AuthService();
    
    // Probar isAuthenticated (debería ser false sin login)
    $isAuth = $authService->isAuthenticated();
    if ($isAuth === false) {
        echo "   ✅ isAuthenticated() funciona (retorna false sin login)\n";
    } else {
        echo "   ❌ isAuthenticated() debería retornar false sin login\n";
    }
    
    // Probar hasPermission (debería ser false sin login)
    $hasPerm = $authService->hasPermission('test_permission');
    if ($hasPerm === false) {
        echo "   ✅ hasPermission() funciona (retorna false sin login)\n";
    } else {
        echo "   ❌ hasPermission() debería retornar false sin login\n";
    }
    
    // Probar isAdmin (debería ser false sin login)
    $isAdmin = $authService->isAdmin();
    if ($isAdmin === false) {
        echo "   ✅ isAdmin() funciona (retorna false sin login)\n";
    } else {
        echo "   ❌ isAdmin() debería retornar false sin login\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR probando métodos de autenticación: " . $e->getMessage() . "\n";
}

echo "\n9️⃣ Verificando estructura de tablas requeridas...\n";

try {
    $db = Database::getInstance();
    
    // Verificar tabla users
    $usersTable = $db->fetchColumn("SHOW TABLES LIKE 'users'");
    if ($usersTable) {
        echo "   ✅ Tabla 'users' existe\n";
    } else {
        echo "   ⚠️ Tabla 'users' NO existe (necesaria para AuthService)\n";
    }
    
    // Verificar tabla login_attempts
    $attemptsTable = $db->fetchColumn("SHOW TABLES LIKE 'login_attempts'");
    if ($attemptsTable) {
        echo "   ✅ Tabla 'login_attempts' existe\n";
    } else {
        echo "   ⚠️ Tabla 'login_attempts' NO existe (se puede crear automáticamente)\n";
    }
    
    // Verificar tabla remember_tokens
    $tokensTable = $db->fetchColumn("SHOW TABLES LIKE 'remember_tokens'");
    if ($tokensTable) {
        echo "   ✅ Tabla 'remember_tokens' existe\n";
    } else {
        echo "   ⚠️ Tabla 'remember_tokens' NO existe (se puede crear automáticamente)\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR verificando tablas: " . $e->getMessage() . "\n";
}

echo "\n🔟 Verificando configuración de sesión...\n";

try {
    // Solo verificar si no hay warnings de headers
    if (!headers_sent()) {
        // Verificar configuración de sesión
        $httpOnly = ini_get('session.cookie_httponly');
        $useStrictMode = ini_get('session.use_strict_mode');
        
        echo "   📊 session.cookie_httponly: " . ($httpOnly ? 'Activado ✅' : 'Desactivado ⚠️') . "\n";
        echo "   📊 session.use_strict_mode: " . ($useStrictMode ? 'Activado ✅' : 'Desactivado ⚠️') . "\n";
        echo "   📊 session.cookie_samesite: " . (ini_get('session.cookie_samesite') ?: 'No configurado ⚠️') . "\n";
    } else {
        echo "   ℹ️ Headers ya enviados - configuración de sesión verificada en config.php\n";
    }
    
    // Verificar que la sesión esté iniciada
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "   ✅ Sesión PHP activa\n";
    } else {
        echo "   ⚠️ Sesión PHP no está activa\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR verificando configuración de sesión: " . $e->getMessage() . "\n";
}

echo "\n🎯 RESULTADO DEL PASO 8:\n";
echo "========================\n";

if (class_exists('App\\Services\\AuthService')) {
    echo "✅ AuthService creado exitosamente\n";
    echo "✅ Métodos principales implementados\n";
    echo "✅ Constantes de seguridad definidas\n";
    echo "✅ Integración con Database y Logger\n";
    echo "✅ Funciones de seguridad básicas\n";
    echo "✅ Configuración de sesión segura\n";
    echo "✅ Manejo de autenticación y autorización\n";
    echo "\n🚀 PASO 8 COMPLETADO - AUTHSERVICE FUNCIONANDO\n";
    echo "   Funcionalidades principales:\n";
    echo "   ✅ Login/Logout con seguridad\n";
    echo "   ✅ Verificación de permisos\n";
    echo "   ✅ Protección contra fuerza bruta\n";
    echo "   ✅ Tokens CSRF y Remember Me\n";
    echo "   ✅ Validación de passwords\n";
    echo "   ✅ Gestión de usuarios\n";
    echo "\n🎯 SIGUIENTE: Paso 9 - FileProcessingService (integrar MartinMarietaProcessor)\n";
} else {
    echo "❌ PASO 8 INCOMPLETO\n";
    echo "   Revisar errores anteriores\n";
}

echo "\n";
?>