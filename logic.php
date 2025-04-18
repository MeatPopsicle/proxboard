<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/api.php';
$config = require_once '/etc/proxmox-dashboard/config.php';

// Validate config
$required_keys = ['proxmox_host', 'username', 'password', 'node'];
foreach ($required_keys as $key) {
    if (!isset($config[$key]) || empty($config[$key])) {
        return ['error' => "Missing or empty config key: $key"];
    }
}

$pinned_file = __DIR__ . '/pinned.json';

// Initialize pinned file
if (!file_exists($pinned_file)) {
    file_put_contents($pinned_file, '{}');
    chmod($pinned_file, 0664);
    chown($pinned_file, 'www-data');
    chgrp($pinned_file, 'www-data');
}

// Fetch resources
$result = ['error' => null, 'pinned_resources' => [], 'unpinned_resources' => [], 'node_status' => []];
$auth = getTicket($config['proxmox_host'], $config['username'], $config['password']);
if (isset($auth['error'])) {
    $result['error'] = $auth['error'];
    return $result;
}

// Fetch node status
$node_status = getNodeStatus($config['proxmox_host'], $config['node'], $auth['ticket']);
if (!isset($node_status['error'])) {
    $result['node_status'] = [
        'node_name' => $node_status['nodename'] ?? $config['node'],
        'cpu_usage' => round($node_status['cpu'] * 100, 2), // Convert to percentage
        'memory_used' => round($node_status['memory']['used'] / (1024 * 1024 * 1024), 2), // Convert to GB
        'memory_total' => round($node_status['memory']['total'] / (1024 * 1024 * 1024), 2), // Convert to GB
        'disk_used' => round($node_status['rootfs']['used'] / (1024 * 1024 * 1024), 2), // Convert to GB
        'disk_total' => round($node_status['rootfs']['total'] / (1024 * 1024 * 1024), 2) // Convert to GB
    ];
}

$qemu_vms = getQemuVMs($config['proxmox_host'], $config['node'], $auth['ticket']);
$lxc_containers = getLxcContainers($config['proxmox_host'], $config['node'], $auth['ticket']);

if (isset($qemu_vms['error']) || isset($lxc_containers['error'])) {
    $result['error'] = $qemu_vms['error'] ?? $lxc_containers['error'];
    return $result;
}

$pinned = json_decode(file_get_contents($pinned_file), true) ?? [];
$all_resources = [];

foreach ($qemu_vms as $vm) {
    $config_data = getConfig($config['proxmox_host'], $config['node'], 'qemu', $vm['vmid'], $auth['ticket']);
    $status = getStatus($config['proxmox_host'], $config['node'], 'qemu', $vm['vmid'], $auth['ticket']);
    if (isset($config_data['error']) || isset($status['error'])) {
        continue;
    }
    $notes = $config_data['description'] ?? '';
    $ip = preg_match('/IP:\s*(\d+\.\d+\.\d+\.\d+)/', $notes, $matches) ? $matches[1] : "10.0.0." . ($vm['vmid'] % 254 + 2);
    $web_port = preg_match('/Port:\s*(\d+)/', $notes, $matches) ? $matches[1] : 80;
    $ssh_port = preg_match('/SSHPort:\s*(\d+)/', $notes, $matches) ? $matches[1] : 22;
    $all_resources[] = [
        'type' => 'QEMU',
        'name' => $vm['name'] ?? 'Unnamed VM ' . $vm['vmid'],
        'vmid' => $vm['vmid'],
        'ip' => $ip,
        'web_port' => $web_port,
        'ssh_port' => $ssh_port,
        'web_url' => "http://$ip:$web_port",
        'ssh_url' => "ssh://$ip:$ssh_port",
        'pinned' => isset($pinned[$vm['vmid']]),
        'status' => $status['status'],
        'cpu_usage' => $status['cpu_usage'],
        'memory_used' => $status['memory_used'],
        'memory_total' => $status['memory_total'],
        'disk_used' => $status['disk_used'],
        'disk_total' => $status['disk_total']
    ];
}

foreach ($lxc_containers as $container) {
    $config_data = getConfig($config['proxmox_host'], $config['node'], 'lxc', $container['vmid'], $auth['ticket']);
    $status = getStatus($config['proxmox_host'], $config['node'], 'lxc', $container['vmid'], $auth['ticket']);
    if (isset($config_data['error']) || isset($status['error'])) {
        continue;
    }
    $notes = $config_data['description'] ?? '';
    $ip = preg_match('/IP:\s*(\d+\.\d+\.\d+\.\d+)/', $notes, $matches) ? $matches[1] : "10.0.0." . ($container['vmid'] % 254 + 2);
    $web_port = preg_match('/Port:\s*(\d+)/', $notes, $matches) ? $matches[1] : 80;
    $ssh_port = preg_match('/SSHPort:\s*(\d+)/', $notes, $matches) ? $matches[1] : 22;
    $all_resources[] = [
        'type' => 'LXC',
        'name' => $container['name'] ?? 'Unnamed Container ' . $container['vmid'],
        'vmid' => $container['vmid'],
        'ip' => $ip,
        'web_port' => $web_port,
        'ssh_port' => $ssh_port,
        'web_url' => "http://$ip:$web_port",
        'ssh_url' => "ssh://$ip:$ssh_port",
        'pinned' => isset($pinned[$container['vmid']]),
        'status' => $status['status'],
        'cpu_usage' => $status['cpu_usage'],
        'memory_used' => $status['memory_used'],
        'memory_total' => $status['memory_total'],
        'disk_used' => $status['disk_used'],
        'disk_total' => $status['disk_total']
    ];
}

// Sort resources by vmid
usort($all_resources, function($a, $b) {
    return $a['vmid'] <=> $b['vmid'];
});

$result['pinned_resources'] = array_filter($all_resources, function($res) { return $res['pinned']; });
$result['unpinned_resources'] = array_filter($all_resources, function($res) { return !$res['pinned']; });

return $result;
?>