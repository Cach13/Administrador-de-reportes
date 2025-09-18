<?php
// ========================================
// test_step_12_repositories.php - TEST DEL PASO 12
// Ejecutar: php test_step_12_repositories.php
// ========================================

echo "🧪 TESTING PASO 12 - REPOSITORIES\n";
echo "==================================\n\n";

require_once 'config/config.php';

$tests = [];
$passed = 0;
$failed = 0;

// ========================================
// TEST 1: CARGA DE REPOSITORIES
// ========================================

echo "1️⃣ Testing: Carga de Repositories\n";

$repositories = ['BaseRepository', 'CompanyRepository', 'VoucherRepository'];
foreach ($repositories as $repo) {
    $className = "App\\Repositories\\$repo";
    try {
        if (class_exists($className)) {
            echo "   ✅ $repo cargado correctamente\n";
            $tests["load_$repo"] = true;
            $passed++;
        } else {
            echo "   ❌ $repo NO se puede cargar\n";
            $tests["load_$repo"] = false;
            $failed++;
        }
    } catch (Exception $e) {
        echo "   ❌ ERROR cargando $repo: " . $e->getMessage() . "\n";
        $tests["load_$repo"] = false;
        $failed++;
    }
}

// ========================================
// TEST 2: INSTANCIACIÓN DE REPOSITORIES
// ========================================

echo "\n2️⃣ Testing: Instanciación de Repositories\n";

try {
    $companyRepo = new App\Repositories\CompanyRepository();
    echo "   ✅ CompanyRepository instanciado\n";
    $tests['company_repo_instantiation'] = true;
    $passed++;
} catch (Exception $e) {
    echo "   ❌ ERROR instanciando CompanyRepository: " . $e->getMessage() . "\n";
    $tests['company_repo_instantiation'] = false;
    $failed++;
}

try {
    $voucherRepo = new App\Repositories\VoucherRepository();
    echo "   ✅ VoucherRepository instanciado\n";
    $tests['voucher_repo_instantiation'] = true;
    $passed++;
} catch (Exception $e) {
    echo "   ❌ ERROR instanciando VoucherRepository: " . $e->getMessage() . "\n";
    $tests['voucher_repo_instantiation'] = false;
    $failed++;
}

// ========================================
// TEST 3: MÉTODOS BÁSICOS CRUD
// ========================================

echo "\n3️⃣ Testing: Métodos CRUD Básicos\n";

try {
    $companyRepo = new App\Repositories\CompanyRepository();
    
    // Test find
    $company = $companyRepo->find(1);
    if ($company) {
        echo "   ✅ CompanyRepository::find() funciona\n";
        echo "   📊 Empresa encontrada: " . $company['name'] . "\n";
        $tests['company_find'] = true;
        $passed++;
    } else {
        echo "   ⚠️ No se encontró empresa con ID 1\n";
        $tests['company_find'] = false;
        $failed++;
    }
    
    // Test all
    $companies = $companyRepo->all();
    echo "   ✅ CompanyRepository::all() devuelve " . count($companies) . " empresas\n";
    $tests['company_all'] = true;
    $passed++;
    
    // Test count
    $count = $companyRepo->count();
    echo "   ✅ CompanyRepository::count() devuelve $count registros\n";
    $tests['company_count'] = true;
    $passed++;
    
} catch (Exception $e) {
    echo "   ❌ ERROR en métodos CRUD: " . $e->getMessage() . "\n";
    $tests['company_crud'] = false;
    $failed++;
}

// ========================================
// TEST 4: MÉTODOS ESPECIALIZADOS
// ========================================

echo "\n4️⃣ Testing: Métodos Especializados\n";

try {
    $companyRepo = new App\Repositories\CompanyRepository();
    
    // Test findByIdentifier
    $javCompany = $companyRepo->findByIdentifier('JAV');
    if ($javCompany) {
        echo "   ✅ CompanyRepository::findByIdentifier('JAV') funciona\n";
        echo "   📊 " . $javCompany['name'] . " - " . $javCompany['capital_percentage'] . "%\n";
        $tests['company_find_by_identifier'] = true;
        $passed++;
    } else {
        echo "   ⚠️ Empresa JAV no encontrada\n";
        $tests['company_find_by_identifier'] = false;
        $failed++;
    }
    
    // Test getActive
    $activeCompanies = $companyRepo->getActive();
    echo "   ✅ CompanyRepository::getActive() devuelve " . count($activeCompanies) . " empresas activas\n";
    $tests['company_get_active'] = true;
    $passed++;
    
    // Test getWithStats
    $companiesWithStats = $companyRepo->getWithStats();
    echo "   ✅ CompanyRepository::getWithStats() devuelve " . count($companiesWithStats) . " empresas con estadísticas\n";
    if (!empty($companiesWithStats)) {
        $firstCompany = $companiesWithStats[0];
        echo "   📊 Ejemplo: " . $firstCompany['name'] . " - " . $firstCompany['total_trips_extracted'] . " trips\n";
    }
    $tests['company_with_stats'] = true;
    $passed++;
    
} catch (Exception $e) {
    echo "   ❌ ERROR en métodos especializados: " . $e->getMessage() . "\n";
    $tests['company_specialized'] = false;
    $failed++;
}

// ========================================
// TEST 5: VOUCHER REPOSITORY
// ========================================

echo "\n5️⃣ Testing: VoucherRepository Métodos\n";

try {
    $voucherRepo = new App\Repositories\VoucherRepository();
    
    // Test all vouchers
    $vouchers = $voucherRepo->all();
    echo "   ✅ VoucherRepository::all() devuelve " . count($vouchers) . " vouchers\n";
    $tests['voucher_all'] = true;
    $passed++;
    
    // Test getByStatus
    $processedVouchers = $voucherRepo->getByStatus('processed');
    echo "   ✅ VoucherRepository::getByStatus('processed') devuelve " . count($processedVouchers) . " vouchers\n";
    $tests['voucher_by_status'] = true;
    $passed++;
    
    // Test getPending
    $pendingVouchers = $voucherRepo->getPending();
    echo "   ✅ VoucherRepository::getPending() devuelve " . count($pendingVouchers) . " vouchers pendientes\n";
    $tests['voucher_pending'] = true;
    $passed++;
    
    // Test findByNumber si hay vouchers
    if (!empty($vouchers)) {
        $firstVoucher = $vouchers[0];
        $foundVoucher = $voucherRepo->findByNumber($firstVoucher['voucher_number']);
        if ($foundVoucher) {
            echo "   ✅ VoucherRepository::findByNumber() funciona\n";
            echo "   📊 Voucher: " . $foundVoucher['voucher_number'] . "\n";
            $tests['voucher_find_by_number'] = true;
            $passed++;
        } else {
            echo "   ❌ VoucherRepository::findByNumber() no encuentra voucher\n";
            $tests['voucher_find_by_number'] = false;
            $failed++;
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR en VoucherRepository: " . $e->getMessage() . "\n";
    $tests['voucher_repository'] = false;
    $failed++;
}

// ========================================
// TEST 6: BÚSQUEDAS Y FILTROS
// ========================================

echo "\n6️⃣ Testing: Búsquedas y Filtros\n";

try {
    $companyRepo = new App\Repositories\CompanyRepository();
    
    // Test search
    $searchResults = $companyRepo->search(['name' => 'Johnson']);
    echo "   ✅ CompanyRepository::search() funciona\n";
    echo "   📊 Búsqueda 'Johnson': " . count($searchResults) . " resultados\n";
    $tests['company_search'] = true;
    $passed++;
    
    // Test paginación
    $paginatedResults = $companyRepo->paginate(1, 2);
    if (isset($paginatedResults['data']) && isset($paginatedResults['pagination'])) {
        echo "   ✅ CompanyRepository::paginate() funciona\n";
        echo "   📊 Página 1, 2 por página: " . count($paginatedResults['data']) . " resultados\n";
        echo "   📊 Total: " . $paginatedResults['pagination']['total'] . " registros\n";
        $tests['company_paginate'] = true;
        $passed++;
    } else {
        echo "   ❌ Paginación no devuelve estructura correcta\n";
        $tests['company_paginate'] = false;
        $failed++;
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR en búsquedas: " . $e->getMessage() . "\n";
    $tests['search_filters'] = false;
    $failed++;
}

// ========================================
// TEST 7: ESTADÍSTICAS
// ========================================

echo "\n7️⃣ Testing: Métodos de Estadísticas\n";

try {
    $companyRepo = new App\Repositories\CompanyRepository();
    
    // Test company stats si hay empresas
    $companies = $companyRepo->all();
    if (!empty($companies)) {
        $firstCompany = $companies[0];
        $stats = $companyRepo->getCompanyStats($firstCompany['id']);
        
        if ($stats) {
            echo "   ✅ CompanyRepository::getCompanyStats() funciona\n";
            echo "   📊 " . $stats['name'] . ": " . $stats['trips_extracted'] . " trips, $" . number_format($stats['total_amount'], 2) . "\n";
            $tests['company_stats'] = true;
            $passed++;
        } else {
            echo "   ⚠️ No hay estadísticas para la empresa\n";
            $tests['company_stats'] = false;
            $failed++;
        }
    }
    
    // Test general stats de repositories
    $generalStats = $companyRepo->getStats();
    if ($generalStats) {
        echo "   ✅ Repository::getStats() funciona\n";
        echo "   📊 Total registros: " . $generalStats['total_records'] . "\n";
        $tests['repo_general_stats'] = true;
        $passed++;
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR en estadísticas: " . $e->getMessage() . "\n";
    $tests['statistics'] = false;
    $failed++;
}

// ========================================
// TEST 8: VALIDACIONES Y UTILIDADES
// ========================================

echo "\n8️⃣ Testing: Validaciones y Utilidades\n";

try {
    $companyRepo = new App\Repositories\CompanyRepository();
    
    // Test validateUniqueIdentifier
    $isUnique = $companyRepo->validateUniqueIdentifier('XYZ'); // Debería ser único
    $isNotUnique = $companyRepo->validateUniqueIdentifier('JAV'); // Debería no ser único
    
    if ($isUnique && !$isNotUnique) {
        echo "   ✅ CompanyRepository::validateUniqueIdentifier() funciona correctamente\n";
        $tests['company_validate_identifier'] = true;
        $passed++;
    } else {
        echo "   ❌ Validación de identificador único no funciona correctamente\n";
        $tests['company_validate_identifier'] = false;
        $failed++;
    }
    
    // Test exists
    $companies = $companyRepo->all();
    if (!empty($companies)) {
        $exists = $companyRepo->exists($companies[0]['id']);
        if ($exists) {
            echo "   ✅ Repository::exists() funciona\n";
            $tests['repo_exists'] = true;
            $passed++;
        } else {
            echo "   ❌ Repository::exists() no funciona\n";
            $tests['repo_exists'] = false;
            $failed++;
        }
    }
    
} catch (Exception $e) {
    echo "   ❌ ERROR en validaciones: " . $e->getMessage() . "\n";
    $tests['validations'] = false;
    $failed++;
}

// ========================================
// RESULTADOS FINALES
// ========================================

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RESULTADOS DEL TEST - PASO 12 REPOSITORIES\n";
echo str_repeat("=", 50) . "\n";

echo "✅ Tests Pasados: $passed\n";
echo "❌ Tests Fallidos: $failed\n";
echo "📊 Total Tests: " . ($passed + $failed) . "\n";

$percentage = $passed > 0 ? round(($passed / ($passed + $failed)) * 100, 2) : 0;
echo "🎯 Porcentaje Éxito: $percentage%\n\n";

if ($percentage >= 90) {
    echo "🎉 PASO 12 REPOSITORIES: EXCELENTE\n";
    echo "   Todos los repositories funcionan correctamente\n";
    echo "   ✅ Listo para PASO 13\n";
} elseif ($percentage >= 70) {
    echo "⚠️  PASO 12 REPOSITORIES: BUENO\n";
    echo "   La mayoría de repositories funcionan\n";
    echo "   📝 Revisar tests fallidos antes de continuar\n";
} else {
    echo "❌ PASO 12 REPOSITORIES: NECESITA REVISIÓN\n";
    echo "   Varios repositories tienen problemas\n";
    echo "   🔧 Arreglar errores antes de continuar\n";
}

echo "\n📋 DETALLES DE TESTS:\n";
foreach ($tests as $test => $result) {
    $status = $result ? "✅ PASS" : "❌ FAIL";
    echo "   $status: $test\n";
}

echo "\n💡 PRÓXIMO PASO:\n";
echo "   📁 PASO 13: Utils mejoradas\n";
echo "   🛠️  Mejorar Database.php y Logger.php\n";
echo "   🔧 Crear ResponseHelper.php\n";

echo "\n";
?>