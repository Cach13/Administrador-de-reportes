<?php
/**
 * test-processing.php
 * Archivo de prueba para la página de procesamiento
 * Simula datos para probar la interfaz sin BD
 */

// Simular datos de vouchers recientes
$recentVouchers = [
    [
        'id' => 1,
        'filename' => 'MM_20250219_001.pdf',
        'status' => 'processed',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
    ],
    [
        'id' => 2,
        'filename' => 'MM_20250219_002.pdf',
        'status' => 'processing',
        'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
    ],
    [
        'id' => 3,
        'filename' => 'MM_20250218_015.pdf',
        'status' => 'processed',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'id' => 4,
        'filename' => 'MM_20250218_014.pdf',
        'status' => 'error',
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'id' => 5,
        'filename' => 'MM_20250217_089.pdf',
        'status' => 'processed',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
    ]
];

// Simular datos de empresas
$companies = [
    [
        'id' => 1,
        'name' => 'Javenes Construction LLC',
        'identifier' => 'JAV',
        'capital_percentage' => 5.0,
        'contact_person' => 'Roberto Javenes',
        'phone' => '(555) 123-4567',
        'is_active' => true
    ],
    [
        'id' => 2,
        'name' => 'ABC Transport Solutions',
        'identifier' => 'ABC',
        'capital_percentage' => 4.5,
        'contact_person' => 'Maria Rodriguez',
        'phone' => '(555) 234-5678',
        'is_active' => true
    ],
    [
        'id' => 3,
        'name' => 'Rodriguez Heavy Haul Inc',
        'identifier' => 'ROD',
        'capital_percentage' => 5.5,
        'contact_person' => 'Carlos Rodriguez',
        'phone' => '(555) 345-6789',
        'is_active' => true
    ],
    [
        'id' => 4,
        'name' => 'Metro Logistics LLC',
        'identifier' => 'MET',
        'capital_percentage' => 4.0,
        'contact_person' => 'Jennifer Smith',
        'phone' => '(555) 456-7890',
        'is_active' => true
    ],
    [
        'id' => 5,
        'name' => 'Pioneer Transport Co',
        'identifier' => 'PIO',
        'capital_percentage' => 6.0,
        'contact_person' => 'David Pioneer',
        'phone' => '(555) 567-8901',
        'is_active' => true
    ],
    [
        'id' => 6,
        'name' => 'Elite Hauling Services',
        'identifier' => 'ELI',
        'capital_percentage' => 4.8,
        'contact_person' => 'Michael Elite',
        'phone' => '(555) 678-9012',
        'is_active' => true
    ],
    [
        'id' => 7,
        'name' => 'Sunrise Transport Group',
        'identifier' => 'SUN',
        'capital_percentage' => 3.5,
        'contact_person' => 'Sarah Johnson',
        'phone' => '(555) 789-0123',
        'is_active' => false
    ]
];

// Datos del usuario actual
$currentUser = [
    'username' => 'admin',
    'role' => 'admin',
    'email' => 'admin@transport.com',
    'full_name' => 'Administrador del Sistema'
];

// Configuración del sistema
$maxFileSize = 20971520; // 20MB
$allowedTypes = ['pdf', 'xlsx', 'xls'];

// Incluir la página de procesamiento
include 'processing.php';
?>