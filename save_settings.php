<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

header('Content-Type: application/json');

if (!isset($_POST['settings'])) {
    echo json_encode(['success' => false, 'error' => 'Missing settings']);
    ob_end_flush();
    exit;
}

$settings_file = __DIR__ . '/settings.json';

if (!file_exists($settings_file)) {
    file_put_contents($settings_file, '{}');
    chmod($settings_file, 0664);
    chown($settings_file, 'www-data');
    chgrp($settings_file, 'www-data');
}

$settings = json_decode($_POST['settings'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'Invalid settings JSON']);
    ob_end_flush();
    exit;
}

if (file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to write to settings.json']);
} else {
    echo json_encode(['success' => true]);
}

ob_end_flush();
exit;
?>