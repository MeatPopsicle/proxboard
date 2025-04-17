<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/api.php';
$config = require_once '/etc/proxmox-dashboard/config.php';

$required_keys = ['proxmox_host', 'username', 'password', 'node'];
foreach ($required_keys as $key) {
    if (!isset($config[$key]) || empty($config[$key])) {
        echo json_encode(['error' => "Missing or empty config key: $key"]);
        ob_end_flush();
        exit;
    }
}

$auth = getTicket($config['proxmox_host'], $config['username'], $config['password']);
if (isset($auth['error'])) {
    echo json_encode(['error' => $auth['error']]);
    ob_end_flush();
    exit;
}

$qemu_vms = getQemuVMs($config['proxmox_host'], $config['node'], $auth['ticket']);
$lxc_containers = getLxcContainers($config['proxmox_host'], $config['node'], $auth['ticket']);

if (isset($qemu_vms['error']) || isset($lxc_containers['error'])) {
    echo json_encode(['error' => $qemu_vms['error'] ?? $lxc_containers['error']]);
    ob_end_flush();
    exit;
}

$statuses = [];
foreach ($qemu_vms as $vm) {
    $status = getStatus($config['proxmox_host'], $config['node'], 'qemu', $vm['vmid'], $auth['ticket']);
    if (!isset($status['error'])) {
        $statuses[$vm['vmid']] = $status;
    }
}
foreach ($lxc_containers as $container) {
    $status = getStatus($config['proxmox_host'], $config['node'], 'lxc', $container['vmid'], $auth['ticket']);
    if (!isset($status['error'])) {
        $statuses[$container['vmid']] = $status;
    }
}

echo json_encode(['success' => true, 'statuses' => $statuses]);
ob_end_flush();
exit;
?>