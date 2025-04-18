<?php
function makeProxmoxRequest($host, $endpoint, $ticket = null, $csrf = null, $method = 'GET', $postFields = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/$endpoint");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    $headers = [];
    if ($ticket) {
        $headers[] = "Cookie: PVEAuthCookie=$ticket";
    }
    if ($csrf) {
        $headers[] = "CSRFPreventionToken: $csrf";
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($postFields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        } else {
            // Explicitly set empty body with Content-Length: 0
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            $headers[] = 'Content-Length: 0';
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " cURL error ($endpoint): $error\n", FILE_APPEND);
        return ['error' => "cURL Error ($endpoint): $error"];
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if ($http_code !== 200) {
        $error = ($data['message'] ?? "HTTP $http_code, no data returned");
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " API error ($endpoint): $error\n", FILE_APPEND);
        return ['error' => "API Error ($endpoint): $error"];
    }

    return $data['data'] ?? [];
}

function getTicket($host, $user, $pass) {
    $postFields = "username=" . urlencode($user) . "&password=" . urlencode($pass);
    $data = makeProxmoxRequest($host, "access/ticket", null, null, 'POST', $postFields);
    if (isset($data['error']) || !isset($data['ticket'])) {
        $error = isset($data['error']) ? $data['error'] : 'Authentication failed: No ticket returned';
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " getTicket error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['ticket' => $data['ticket'], 'csrf' => $data['CSRFPreventionToken']];
}

function getNodeStatus($host, $node, $ticket) {
    $data = makeProxmoxRequest($host, "nodes/$node/status", $ticket);
    return $data ?: ['error' => 'No node status data returned'];
}

function getQemuVMs($host, $node, $ticket) {
    return makeProxmoxRequest($host, "nodes/$node/qemu", $ticket);
}

function getLxcContainers($host, $node, $ticket) {
    return makeProxmoxRequest($host, "nodes/$node/lxc", $ticket);
}

function getConfig($host, $node, $type, $vmid, $ticket) {
    return makeProxmoxRequest($host, "nodes/$node/$type/$vmid/config", $ticket);
}

function getStatus($host, $node, $type, $vmid, $ticket) {
    $data = makeProxmoxRequest($host, "nodes/$node/$type/$vmid/status/current", $ticket);
    if (isset($data['error'])) {
        return ['error' => $data['error']];
    }
    return [
        'status' => $data['status'] ?? 'unknown',
        'cpu_usage' => isset($data['cpu']) ? round($data['cpu'] * 100, 2) : 0,
        'memory_used' => isset($data['mem']) ? round($data['mem'] / (1024 * 1024 * 1024), 2) : 0,
        'memory_total' => isset($data['maxmem']) ? round($data['maxmem'] / (1024 * 1024 * 1024), 2) : 0,
        'disk_used' => isset($data['disk']) ? round($data['disk'] / (1024 * 1024 * 1024), 2) : 0,
        'disk_total' => isset($data['maxdisk']) ? round($data['maxdisk'] / (1024 * 1024 * 1024), 2) : 0
    ];
}

function startVM($host, $node, $type, $vmid, $ticket, $csrf) {
    $endpoint = "nodes/$node/$type/$vmid/status/start";
    $data = makeProxmoxRequest($host, $endpoint, $ticket, $csrf, 'POST');
    if (isset($data['error']) || !isset($data)) {
        $error = isset($data['error']) ? $data['error'] : 'Start failed: No data returned';
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " startVM error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['success' => true, 'data' => $data];
}

function stopVM($host, $node, $type, $vmid, $ticket, $csrf) {
    $endpoint = "nodes/$node/$type/$vmid/status/stop";
    $data = makeProxmoxRequest($host, $endpoint, $ticket, $csrf, 'POST');
    if (isset($data['error']) || !isset($data)) {
        $error = isset($data['error']) ? $data['error'] : 'Stop failed: No data returned';
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " stopVM error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['success' => true, 'data' => $data];
}

function shutdownVM($host, $node, $type, $vmid, $ticket, $csrf) {
    $endpoint = "nodes/$node/$type/$vmid/status/shutdown";
    $data = makeProxmoxRequest($host, $endpoint, $ticket, $csrf, 'POST');
    if (isset($data['error']) || !isset($data)) {
        $error = isset($data['error']) ? $data['error'] : 'Shutdown failed: No data returned';
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " shutdownVM error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['success' => true, 'data' => $data];
}

function restartVM($host, $node, $type, $vmid, $ticket, $csrf) {
    $endpoint = "nodes/$node/$type/$vmid/status/reboot";
    $data = makeProxmoxRequest($host, $endpoint, $ticket, $csrf, 'POST');
    if (isset($data['error']) || !isset($data)) {
        $error = isset($data['error']) ? $data['error'] : 'Restart failed: No data returned';
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " restartVM error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['success' => true, 'data' => $data];
}
?>