<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Start output buffering

header('Content-Type: application/json');

// Debug log
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " POST received: " . print_r($_POST, true) . "\n", FILE_APPEND);

if (!isset($_POST['vmid']) || !isset($_POST['pin'])) {
    echo json_encode(['success' => false, 'error' => 'Missing vmid or pin']);
    ob_end_flush();
    exit;
}

$pinned_file = __DIR__ . '/pinned.json';

if (!file_exists($pinned_file)) {
    file_put_contents($pinned_file, '{}');
    chmod($pinned_file, 0664);
    chown($pinned_file, 'www-data');
    chgrp($pinned_file, 'www-data');
}
$pinned = json_decode(file_get_contents($pinned_file), true) ?? [];
$vmid = (int)$_POST['vmid'];
if ($_POST['pin'] === 'true') {
    $pinned[$vmid] = true;
} else {
    unset($pinned[$vmid]);
}
if (file_put_contents($pinned_file, json_encode($pinned)) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to write to pinned.json']);
} else {
    echo json_encode(['success' => true]);
}

ob_end_flush();
exit;
?>