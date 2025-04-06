<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxmox VM & LXC Dashboard</title>
    <style>
        body { background-color: #2d2d2d; color: #ffffff; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .pinned-section { margin-bottom: 30px; }
        .vm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .vm-card { background-color: #3a3a3a; padding: 15px; border-radius: 5px; text-align: center; transition: transform 0.2s; }
        .vm-card:hover { transform: scale(1.05); background-color: #444444; }
        .vm-card a { color: #4da8da; text-decoration: none; margin: 0 5px; }
        .pin-btn { background: none; border: none; color: #ffd700; cursor: pointer; font-size: 16px; }
        h2 { color: #ffffff; border-bottom: 1px solid #4da8da; padding-bottom: 5px; }
        .type-label { font-size: 12px; color: #aaaaaa; }
        .error { color: #ff4444; }
        .status-indicator { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
        .status-running { background-color: #00ff00; }
        .status-stopped { background-color: #ff0000; }
        .button-group { margin-top: 10px; }
        .action-link { color: #4da8da; text-decoration: none; transition: color 0.2s; }
        .action-link:hover { color: #ffffff; text-decoration: underline; }
        .header { display: flex; justify-content: center; align-items: center; position: relative; }
        .header h3 { margin: 0; }
        .status-left { position: absolute; left: 0; }
        .pin-right { position: absolute; right: 0; }
    </style>
</head>
<body>
    <div class="container">
        <?php
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        $config = require_once __DIR__ . '/config.php';
        $proxmox_host = $config['proxmox_host'];
        $username = $config['username'];
        $password = $config['password'];
        $node = $config['node'];
        $pinned_file = __DIR__ . '/pinned.json';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vmid']) && isset($_POST['pin'])) {
            header('Content-Type: application/json');
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
            exit;
        }

        if (!file_exists($pinned_file)) {
            file_put_contents($pinned_file, '{}');
            chmod($pinned_file, 0664);
            chown($pinned_file, 'www-data');
            chgrp($pinned_file, 'www-data');
        }

        function getTicket($host, $user, $pass) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/access/ticket");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "username=" . urlencode($user) . "&password=" . urlencode($pass));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if ($response === false) { echo "<p class='error'>cURL Error (getTicket): " . curl_error($ch) . "</p>"; curl_close($ch); return false; }
            curl_close($ch);
            $data = json_decode($response, true);
            return isset($data['data']['ticket']) ? ['ticket' => $data['data']['ticket'], 'csrf' => $data['data']['CSRFPreventionToken']] : false;
        }

        function getQemuVMs($host, $node, $ticket) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/nodes/$node/qemu");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: PVEAuthCookie=$ticket"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if ($response === false) { echo "<p class='error'>cURL Error (getQemuVMs): " . curl_error($ch) . "</p>"; curl_close($ch); return []; }
            curl_close($ch);
            $data = json_decode($response, true);
            return $data['data'] ?? [];
        }

        function getLxcContainers($host, $node, $ticket) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/nodes/$node/lxc");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: PVEAuthCookie=$ticket"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if ($response === false) { echo "<p class='error'>cURL Error (getLxcContainers): " . curl_error($ch) . "</p>"; curl_close($ch); return []; }
            curl_close($ch);
            $data = json_decode($response, true);
            return $data['data'] ?? [];
        }

        function getConfig($host, $node, $type, $vmid, $ticket) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/nodes/$node/$type/$vmid/config");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: PVEAuthCookie=$ticket"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);
            return $data['data'] ?? [];
        }

        function getStatus($host, $node, $type, $vmid, $ticket) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/nodes/$node/$type/$vmid/status/current");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: PVEAuthCookie=$ticket"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($response, true);
            return $data['data']['status'] ?? 'unknown';
        }

        $auth = getTicket($proxmox_host, $username, $password);
        if ($auth === false) {
            echo "<p class='error'>Stopping due to auth failure.</p>";
        } else {
            $qemu_vms = getQemuVMs($proxmox_host, $node, $auth['ticket']);
            $lxc_containers = getLxcContainers($proxmox_host, $node, $auth['ticket']);
            $pinned = json_decode(file_get_contents($pinned_file), true) ?? [];

            $all_resources = [];
            foreach ($qemu_vms as $vm) {
                $config = getConfig($proxmox_host, $node, 'qemu', $vm['vmid'], $auth['ticket']);
                $status = getStatus($proxmox_host, $node, 'qemu', $vm['vmid'], $auth['ticket']);
                $notes = $config['description'] ?? '';
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
                $config = getConfig($proxmox_host, $node, 'lxc', $container['vmid'], $auth['ticket']);
                $status = getStatus($proxmox_host, $node, 'lxc', $container['vmid'], $auth['ticket']);
                $notes = $config['description'] ?? '';
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

            $pinned_resources = array_filter($all_resources, function($res) { return $res['pinned']; });
            $unpinned_resources = array_filter($all_resources, function($res) { return !$res['pinned']; });
        ?>

        <div class="pinned-section">
            <h2>Pinned Servers</h2>
            <div class="vm-grid">
                <?php if (empty($pinned_resources)): ?>
                    <p>No pinned resources found.</p>
                <?php else: ?>
                    <?php foreach ($pinned_resources as $res): ?>
                        <div class="vm-card" data-vmid="<?php echo $res['vmid']; ?>">
                            <div class="header">
                                <span class="status-left">
                                    <span class="status-indicator <?php echo $res['status'] === 'running' ? 'status-running' : 'status-stopped'; ?>"></span>
                                </span>
                                <h3><?php echo htmlspecialchars($res['name']); ?></h3>
                                <span class="pin-right">
                                    <button class="pin-btn">★</button>
                                </span>
                            </div>
                            <p><?php echo htmlspecialchars($res['ip'] . ':' . $res['web_port']); ?></p>
                            <div class="type-label"><?php echo $res['type']; ?></div>
                            <div class="button-group">
                                <a href="<?php echo htmlspecialchars($res['web_url']); ?>" target="_blank" class="action-link">Web</a>
                                <a href="<?php echo htmlspecialchars($res['ssh_url']); ?>" target="_blank" class="action-link">SSH</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h2>All Servers</h2>
            <div class="vm-grid">
                <?php if (empty($unpinned_resources)): ?>
                    <p>No unpinned resources found.</p>
                <?php else: ?>
                    <?php foreach ($unpinned_resources as $res): ?>
                        <div class="vm-card" data-vmid="<?php echo $res['vmid']; ?>">
                            <div class="header">
                                <span class="status-left">
                                    <span class="status-indicator <?php echo $res['status'] === 'running' ? 'status-running' : 'status-stopped'; ?>"></span>
                                </span>
                                <h3><?php echo htmlspecialchars($res['name']); ?></h3>
                                <span class="pin-right">
                                    <button class="pin-btn">☆</button>
                                </span>
                            </div>
                            <p><?php echo htmlspecialchars($res['ip'] . ':' . $res['web_port']); ?></p>
                            <div class="type-label"><?php echo $res['type']; ?></div>
                            <div class="button-group">
                                <a href="<?php echo htmlspecialchars($res['web_url']); ?>" target="_blank" class="action-link">Web</a>
                                <a href="<?php echo htmlspecialchars($res['ssh_url']); ?>" target="_blank" class="action-link">SSH</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php } ?>
    </div>

    <script>
        document.querySelectorAll('.pin-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const card = btn.closest('.vm-card');
                const vmid = card.dataset.vmid;
                const isPinned = btn.textContent === '★';
                btn.textContent = isPinned ? '☆' : '★';
                btn.style.color = isPinned ? '#ffffff' : '#ffd700';

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `vmid=${vmid}&pin=${!isPinned}`
                }).then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                }).then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        console.error('Pin update failed:', data.error);
                    }
                }).catch(err => console.error('Pin update failed:', err));
            });
        });
    </script>
</body>
</html>