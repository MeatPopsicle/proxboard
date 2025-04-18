<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/api.php';
$config = require_once '/etc/proxmox-dashboard/config.php';

if (!isset($_POST['vmid']) || !isset($_POST['type']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'error' => 'Missing vmid, type, or action']);
    ob_end_flush();
    exit;
}

$vmid = (int)$_POST['vmid'];
$type = strtolower($_POST['type']);
$action = $_POST['action'];

if (!in_array($type, ['qemu', 'lxc']) || !in_array($action, ['start', 'stop', 'shutdown', 'restart'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid type or action']);
    ob_end_flush();
    exit;
}

$auth = getTicket($config['proxmox_host'], $config['username'], $config['password']);
if (isset($auth['error'])) {
    file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " Auth error: {$auth['error']} (username={$config['username']})\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => "Authentication error: {$auth['error']}"]);
    ob_end_flush();
    exit;
}

$result = match ($action) {
    'start' => startVM($config['proxmox_host'], $config['node'], $type, $vmid, $auth['ticket'], $auth['csrf']),
    'stop' => stopVM($config['proxmox_host'], $config['node'], $type, $vmid, $auth['ticket'], $auth['csrf']),
    'shutdown' => shutdownVM($config['proxmox_host'], $config['node'], $type, $vmid, $auth['ticket'], $auth['csrf']),
    'restart' => restartVM($config['proxmox_host'], $config['node'], $type, $vmid, $auth['ticket'], $auth['csrf'])
};

if (isset($result['error'])) {
    echo json_encode(['success' => false, 'error' => $result['error']]);
} else {
    echo json_encode(['success' => true]);
}

ob_end_flush();
exit;
?>