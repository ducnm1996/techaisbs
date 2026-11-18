<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$base = dirname(__DIR__);
$checks = [
    'php_version' => PHP_VERSION,
    'php_supported' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'vendor_present' => is_file($base.'/vendor/autoload.php'),
    'env_present' => is_file($base.'/.env'),
    'configuration_cached' => is_file($base.'/bootstrap/cache/config.php'),
    'pdo_mysql_available' => extension_loaded('pdo_mysql'),
    'storage_writable' => is_writable($base.'/storage'),
    'views_writable' => is_writable($base.'/storage/framework/views'),
    'bootstrap_cache_writable' => is_writable($base.'/bootstrap/cache'),
];

if ($checks['vendor_present']) {
    try {
        require $base.'/vendor/autoload.php';
        $checks['autoload_ready'] = true;
    } catch (Throwable $error) {
        $checks['autoload_ready'] = false;
        $checks['autoload_error_type'] = get_class($error);
    }
}

echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
