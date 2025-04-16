<?php
function getTicket($host, $user, $pass) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$host/api2/json/access/ticket");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "username=" . urlencode($user) . "&password=" . urlencode($pass));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => "cURL Error (getTicket): $error"];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (!isset($data['data']['ticket'])) {
        return ['error' => 'Authentication failed: ' . ($data['message'] ?? 'No ticket returned')];
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
?>