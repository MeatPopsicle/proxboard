<?php
function getTicket($host, $user, $pass) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/access/ticket");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "username=" . urlencode($user) . "&password=" . urlencode($pass));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " getTicket error: $error\n", FILE_APPEND);
        return ['error' => "cURL Error (getTicket): $error"];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if ($http_code !== 200 || !isset($data['data']['ticket'])) {
        $error = 'Authentication failed: ' . ($data['message'] ?? "HTTP $http_code, no ticket returned");
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " getTicket error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['ticket' => $data['data']['ticket'], 'csrf' => $data['data']['CSRFPreventionToken']];
}

function getQemuVMs($host, $node, $ticket) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/nodes/$node/qemu");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: PVEAuthCookie=$ticket"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => "cURL Error (getQemuVMs): $error"];
    }
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => "cURL Error (getLxcContainers): $error"];
    }
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => "cURL Error (getConfig): $error"];
    }
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => "cURL Error (getStatus): $error"];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['data']['status'] ?? 'unknown';
}

function startVM($host, $node, $type, $vmid, $ticket, $csrf) {
    $ch = curl_init();
    $endpoint = $type === 'qemu' 
        ? "https://$host/api2/json/nodes/$node/qemu/$vmid/status/start"
        : "https://$host/api2/json/nodes/$node/lxc/$vmid/status/start";
    $headers = [
        "Cookie: PVEAuthCookie=$ticket",
        "CSRFPreventionToken: $csrf"
    ];
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " startVM error: $error\n", FILE_APPEND);
        return ['error' => "cURL Error (startVM): $error"];
    }
    curl_close($ch);
    $data = json_decode($response, true) ?? [];
    if ($http_code !== 200 || !isset($data['data'])) {
        $error = 'Start failed: ' . ($data['message'] ?? "HTTP $http_code, no data returned");
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " startVM error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['success' => true, 'data' => $data['data']];
}

function stopVM($host, $node, $type, $vmid, $ticket, $csrf) {
    $ch = curl_init();
    $endpoint = $type === 'qemu' 
        ? "https://$host/api2/json/nodes/$node/qemu/$vmid/status/stop"
        : "https://$host/api2/json/nodes/$node/lxc/$vmid/status/stop";
    $headers = [
        "Cookie: PVEAuthCookie=$ticket",
        "CSRFPreventionToken: $csrf"
    ];
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " stopVM error: $error\n", FILE_APPEND);
        return ['error' => "cURL Error (stopVM): $error"];
    }
    curl_close($ch);
    $data = json_decode($response, true) ?? [];
    if ($http_code !== 200 || !isset($data['data'])) {
        $error = 'Stop failed: ' . ($data['message'] ?? "HTTP $http_code, no data returned");
        file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " stopVM error: $error\n", FILE_APPEND);
        return ['error' => $error];
    }
    return ['success' => true, 'data' => $data['data']];
}
?>