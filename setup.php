#!/usr/bin/env php
<?php
/**
 * Quick Setup Script for SOP Recorder
 * Run this script to verify your setup
 */

echo "\n";
echo "═══════════════════════════════════════════════\n";
echo "   SOP Recorder - Setup Verification Script   \n";
echo "═══════════════════════════════════════════════\n\n";

$errors = [];
$warnings = [];

// Check PHP version
echo "✓ Checking PHP version...\n";
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $errors[] = "PHP 7.4 or higher required. Current: " . PHP_VERSION;
} else {
    echo "  PHP version: " . PHP_VERSION . " ✓\n";
}

// Check required extensions
echo "\n✓ Checking PHP extensions...\n";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Required PHP extension missing: $ext";
    } else {
        echo "  $ext ✓\n";
    }
}

// Check directories
echo "\n✓ Checking directories...\n";
$dirs = [
    'backend/uploads',
    'backend/uploads/sessions',
    'backend/config',
    'backend/api',
    'web-app/js',
    'web-app/css',
    'chrome-extension/scripts'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        $errors[] = "Directory missing: $dir";
    } else {
        echo "  $dir ✓\n";
    }
}

// Check writable directories
echo "\n✓ Checking write permissions...\n";
$writable_dirs = [
    'backend/uploads',
    'backend/uploads/sessions'
];

foreach ($writable_dirs as $dir) {
    if (!is_writable($dir)) {
        $warnings[] = "Directory not writable: $dir (run: chmod 777 $dir)";
    } else {
        echo "  $dir ✓\n";
    }
}

// Check configuration files
echo "\n✓ Checking configuration files...\n";
$config_files = [
    'backend/config/config.php',
    'backend/config/database.php',
    'database/schema.sql'
];

foreach ($config_files as $file) {
    if (!file_exists($file)) {
        $errors[] = "Configuration file missing: $file";
    } else {
        echo "  $file ✓\n";
    }
}

// Test database connection
echo "\n✓ Testing database connection...\n";
if (file_exists('backend/config/database.php')) {
    require_once 'backend/config/database.php';

    try {
        $db = Database::getInstance()->getConnection();
        echo "  Database connection successful ✓\n";

        // Check if tables exist
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($tables) < 13) {
            $warnings[] = "Expected 13 tables, found " . count($tables) . ". Run: mysql -u root -p sop_recorder < database/schema.sql";
        } else {
            echo "  Database tables: " . count($tables) . " ✓\n";
        }

    } catch (Exception $e) {
        $warnings[] = "Database connection failed: " . $e->getMessage();
        echo "  ⚠ Check database credentials in backend/config/database.php\n";
    }
}

// Summary
echo "\n═══════════════════════════════════════════════\n";
echo "                  SUMMARY                      \n";
echo "═══════════════════════════════════════════════\n\n";

if (count($errors) > 0) {
    echo "❌ ERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "  • $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  • $warning\n";
    }
    echo "\n";
}

if (count($errors) === 0 && count($warnings) === 0) {
    echo "✅ All checks passed! Your setup looks good.\n\n";
    echo "Next steps:\n";
    echo "1. Start backend server: cd backend && php -S localhost:8000\n";
    echo "2. Start web app server: cd web-app && php -S localhost:8080\n";
    echo "3. Load extension in Chrome: chrome://extensions/\n";
    echo "4. Open http://localhost:8080/pages/login.html\n\n";
} elseif (count($errors) === 0) {
    echo "✅ Setup is mostly complete. Address warnings above.\n\n";
} else {
    echo "❌ Please fix errors above before proceeding.\n\n";
}

echo "For detailed instructions, see SETUP.md\n\n";
