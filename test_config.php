<?php
// ========================================
// scripts/create-app-structure.php
// PASO 3: Crear estructura completa app/
// ========================================

echo "🏗️ CREANDO ESTRUCTURA APP/ - PASO 3\n";
echo "=====================================\n\n";

// Definir estructura de directorios
$directories = [
    // Directorio principal app/
    'app',
    
    // Controllers - Lógica de negocio
    'app/Controllers',
    
    // Services - Servicios centrales
    'app/Services',
    
    // Models - Modelos de datos
    'app/Models',
    
    // Repositories - Acceso a datos
    'app/Repositories',
    
    // Utils - Utilidades mejoradas
    'app/Utils',
    
    // Middleware - Funciones intermedias
    'app/Middleware',
    
    // Exceptions - Manejo de errores personalizados
    'app/Exceptions',
    
    // Views - Templates (para el futuro)
    'views',
    'views/layouts',
    'views/components',
    'views/pages',
    
    // Public - Archivos web accesibles
    'public',
    'public/assets',
    'public/assets/css',
    'public/assets/js',
    'public/assets/img',
    'public/uploads',
    'public/uploads/vouchers',
    'public/uploads/reports',
    'public/uploads/temp',
    
    // Storage - Almacenamiento interno
    'storage',
    'storage/logs',
    'storage/cache',
    'storage/sessions',
    'storage/temp',
    'storage/backup',
    
    // Database - Scripts de BD
    'database',
    'database/migrations',
    'database/seeds',
    
    // Scripts - Utilidades de mantenimiento
    'scripts',
    
    // Tests - Pruebas (futuro)
    'tests',
    'tests/Unit',
    'tests/Feature'
];

$created = 0;
$existing = 0;
$errors = 0;

echo "📁 Creando directorios:\n";
echo "----------------------\n";

foreach ($directories as $dir) {
    $fullPath = __DIR__ . '/../' . $dir;
    
    if (is_dir($fullPath)) {
        echo "   ✅ {$dir} (ya existe)\n";
        $existing++;
    } else {
        if (mkdir($fullPath, 0755, true)) {
            echo "   🆕 {$dir} (creado)\n";
            $created++;
        } else {
            echo "   ❌ {$dir} (error)\n";
            $errors++;
        }
    }
}

echo "\n";

// Crear archivos .gitkeep para directorios vacíos
echo "📄 Creando archivos .gitkeep:\n";
echo "-----------------------------\n";

$gitkeepDirs = [
    'storage/logs',
    'storage/cache', 
    'storage/sessions',
    'storage/temp',
    'storage/backup',
    'public/uploads/vouchers',
    'public/uploads/reports',
    'public/uploads/temp',
    'tests/Unit',
    'tests/Feature'
];

$gitkeepCreated = 0;

foreach ($gitkeepDirs as $dir) {
    $gitkeepPath = __DIR__ . '/../' . $dir . '/.gitkeep';
    $dirPath = __DIR__ . '/../' . $dir;
    
    if (is_dir($dirPath)) {
        if (!file_exists($gitkeepPath)) {
            if (file_put_contents($gitkeepPath, '# Keep this directory in git')) {
                echo "   🆕 {$dir}/.gitkeep (creado)\n";
                $gitkeepCreated++;
            } else {
                echo "   ❌ {$dir}/.gitkeep (error)\n";
            }
        } else {
            echo "   ✅ {$dir}/.gitkeep (ya existe)\n";
        }
    }
}

echo "\n";

// Crear índex.php de seguridad para directorios sensibles
echo "🔒 Creando archivos de seguridad:\n";
echo "--------------------------------\n";

$securityDirs = [
    'app',
    'app/Controllers',
    'app/Services', 
    'app/Models',
    'app/Repositories',
    'app/Utils',
    'storage',
    'storage/logs',
    'storage/cache',
    'database'
];

$securityContent = '<?php
// ========================================
// SECURITY: Deny direct access
// ========================================
http_response_code(403);
die("Access Denied");
?>';

$securityCreated = 0;

foreach ($securityDirs as $dir) {
    $securityPath = __DIR__ . '/../' . $dir . '/index.php';
    $dirPath = __DIR__ . '/../' . $dir;
    
    if (is_dir($dirPath)) {
        if (!file_exists($securityPath)) {
            if (file_put_contents($securityPath, $securityContent)) {
                echo "   🔒 {$dir}/index.php (creado)\n";
                $securityCreated++;
            } else {
                echo "   ❌ {$dir}/index.php (error)\n";
            }
        } else {
            echo "   ✅ {$dir}/index.php (ya existe)\n";
        }
    }
}

echo "\n";

// Verificar permisos
echo "🔍 Verificando permisos:\n";
echo "----------------------\n";

$permissionDirs = [
    'storage/logs' => 'Logs del sistema',
    'storage/cache' => 'Cache de aplicación',
    'public/uploads' => 'Uploads de usuarios',
    'storage/temp' => 'Archivos temporales'
];

foreach ($permissionDirs as $dir => $description) {
    $fullPath = __DIR__ . '/../' . $dir;
    
    if (is_dir($fullPath)) {
        if (is_writable($fullPath)) {
            echo "   ✅ {$dir} - Escribible ✓\n";
        } else {
            echo "   ⚠️ {$dir} - Solo lectura (puede necesitar chmod 755)\n";
        }
    } else {
        echo "   ❌ {$dir} - No existe\n";
    }
}

echo "\n";

// Estadísticas finales
echo "📊 ESTADÍSTICAS FINALES:\n";
echo "=======================\n";
echo "📁 Directorios creados: {$created}\n";
echo "📁 Directorios existentes: {$existing}\n";
echo "📄 Archivos .gitkeep: {$gitkeepCreated}\n";
echo "🔒 Archivos de seguridad: {$securityCreated}\n";

if ($errors > 0) {
    echo "❌ Errores: {$errors}\n";
    echo "\n⚠️ ATENCIÓN: Hubo errores creando algunos directorios.\n";
    echo "   Verifica permisos y ejecuta como administrador si es necesario.\n";
} else {
    echo "✅ Errores: 0\n";
    echo "\n🎉 ESTRUCTURA APP/ CREADA EXITOSAMENTE\n";
    echo "   ✅ Todos los directorios necesarios están listos\n";
    echo "   ✅ Archivos de seguridad instalados\n";
    echo "   ✅ Permisos verificados\n";
    echo "\n🚀 PASO 3 COMPLETADO - LISTO PARA PASO 4\n";
    echo "   Siguiente: Crear BaseController\n";
}

echo "\n";

// Mostrar estructura creada
echo "🌳 ESTRUCTURA FINAL:\n";
echo "===================\n";
echo "transport-management/\n";
echo "├── app/                    # 🆕 Aplicación principal\n";
echo "│   ├── Controllers/        # 🆕 Controladores\n";
echo "│   ├── Services/           # 🆕 Lógica de negocio\n";
echo "│   ├── Models/             # 🆕 Modelos de datos\n";
echo "│   ├── Repositories/       # 🆕 Acceso a datos\n";
echo "│   ├── Utils/              # 🆕 Utilidades\n";
echo "│   ├── Middleware/         # 🆕 Middleware\n";
echo "│   └── Exceptions/         # 🆕 Excepciones\n";
echo "├── classes/                # ✅ Tus clases actuales\n";
echo "├── config/                 # ✅ Configuración\n";
echo "├── public/                 # 🆕 Archivos públicos\n";
echo "├── storage/                # 🆕 Almacenamiento\n";
echo "├── views/                  # 🆕 Templates\n";
echo "├── database/               # 🆕 Scripts BD\n";
echo "└── vendor/                 # ✅ Composer\n";

?>