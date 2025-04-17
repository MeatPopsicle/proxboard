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
$result = ['error' => null, 'pinned_resources' => [], 'unpinned_resources' => []];
$auth = getTicket($config['proxmox_host'], $config['username'], $config['password']);
if (isset($auth['error'])) {
    $result['error'] = $auth['error'];
    return $result;
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
        'status' => $status
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
        'status' => $status
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