<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxmox VM & LXC Dashboard</title>
    <link rel="stylesheet" href="/proxboard/styles.css">
    <link rel="icon" type="image/x-icon" href="/proxboard/favicon.png">
</head>
<body class="<?php
    $default_settings = ['showNodeMetrics' => true, 'showVMMetrics' => true, 'sortOrder' => 'asc', 'theme' => 'dark'];
    $loaded_settings = json_decode(file_get_contents(__DIR__ . '/settings.json'), true) ?? [];
    $settings = array_merge($default_settings, $loaded_settings);
    echo htmlspecialchars($settings['theme']) . '-theme';
?>">
    <div class="container">
        <?php
        $data = require_once __DIR__ . '/logic.php';
        if ($data['error']) {
            echo "<p class='error'>" . htmlspecialchars($data['error']) . "</p>";
        } else {
            // Sort resources server-side based on sortOrder
            $sort_function = function($a, $b) use ($settings) {
                return $settings['sortOrder'] === 'asc' ? $a['vmid'] <=> $b['vmid'] : $b['vmid'] <=> $a['vmid'];
            };
            usort($data['pinned_resources'], $sort_function);
            usort($data['unpinned_resources'], $sort_function);
        ?>
            <div class="node-status-bar">
                <h2>Node: <?php echo htmlspecialchars($data['node_status']['node_name'] ?? 'Unknown'); ?></h2>
                <div class="node-metrics <?php echo !$settings['showNodeMetrics'] ? 'hidden' : ''; ?>">
                    <span>
                        <span class="metric-label">CPU</span>
                        <span id="cpu-usage"><?php echo htmlspecialchars($data['node_status']['cpu_usage'] ?? 'N/A'); ?>%</span>
                        <div class="node-progress-bar">
                            <div class="progress cpu-progress" style="width: <?php echo ($data['node_status']['cpu_usage'] ?? 0); ?>%;"></div>
                        </div>
                    </span>
                    <span>
                        <span class="metric-label">RAM</span>
                        <span id="ram-usage"><?php echo htmlspecialchars($data['node_status']['memory_used'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($data['node_status']['memory_total'] ?? 'N/A'); ?> GB</span>
                        <div class="node-progress-bar">
                            <div class="progress ram-progress" style="width: <?php echo ($data['node_status']['memory_total'] > 0 ? ($data['node_status']['memory_used'] / $data['node_status']['memory_total'] * 100) : 0); ?>%;"></div>
                        </div>
                    </span>
                    <span>
                        <span class="metric-label">Disk</span>
                        <span id="disk-usage"><?php echo htmlspecialchars($data['node_status']['disk_used'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($data['node_status']['disk_total'] ?? 'N/A'); ?> GB</span>
                        <div class="node-progress-bar">
                            <div class="progress disk-progress" style="width: <?php echo ($data['node_status']['disk_total'] > 0 ? ($data['node_status']['disk_used'] / $data['node_status']['disk_total'] * 100) : 0); ?>%;"></div>
                        </div>
                    </span>
                </div>
                <span class="settings-right">
                    <button class="settings-btn" title="Settings">⚙️</button>
                </span>
            </div>
            <div class="pinned-section">
                <h2>Pinned Servers</h2>
                <div class="vm-grid" id="pinned-grid">
                    <?php if (empty($data['pinned_resources'])): ?>
                        <p>No pinned resources found.</p>
                    <?php else: ?>
                        <?php foreach ($data['pinned_resources'] as $res): ?>
                            <div class="vm-card" data-vmid="<?php echo $res['vmid']; ?>" data-type="<?php echo $res['type']; ?>">
                                <div class="vm-card-content">
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
                                    <div class="type-label"><?php echo $res['type'] . ' (' . $res['vmid'] . ')'; ?></div>
                                    <?php if ($res['status'] === 'running' && $settings['showVMMetrics']): ?>
                                        <div class="vm-metrics">
                                            <div class="progress-bar">
                                                <span class="metric-icon">🖥️</span>
                                                <div class="progress cpu-progress" style="width: <?php echo $res['cpu_usage']; ?>%;"></div>
                                            </div>
                                            <div class="progress-bar">
                                                <span class="metric-icon">🧪</span>
                                                <div class="progress ram-progress" style="width: <?php echo ($res['memory_total'] > 0 ? ($res['memory_used'] / $res['memory_total'] * 100) : 0); ?>%;"></div>
                                            </div>
                                            <div class="progress-bar">
                                                <span class="metric-icon">💾</span>
                                                <div class="progress disk-progress" style="width: <?php echo ($res['disk_total'] > 0 ? ($res['disk_used'] / $res['disk_total'] * 100) : 0); ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="vm-card-actionbar">
                                    <a href="<?php echo htmlspecialchars($res['web_url']); ?>" target="_blank" class="action-btn" title="Web">🌐</a>
                                    <a href="<?php echo htmlspecialchars($res['ssh_url']); ?>" target="_blank" class="action-btn ssh-btn" title="SSH">🖥️</a>
                                    <?php if ($res['status'] === 'running'): ?>
                                        <button class="action-btn restart-btn" title="Restart">🔄</button>
                                        <button class="action-btn shutdown-btn" title="Shutdown">⏻</button>
                                    <?php else: ?>
                                        <button class="action-btn start-btn" title="Start">▶</button>
                                    <?php endif; ?>
                                    <button class="action-btn stop-btn" title="Force Stop">■</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h2>All Servers</h2>
                <div class="vm-grid" id="unpinned-grid">
                    <?php if (empty($data['unpinned_resources'])): ?>
                        <p>No unpinned resources found.</p>
                    <?php else: ?>
                        <?php foreach ($data['unpinned_resources'] as $res): ?>
                            <div class="vm-card" data-vmid="<?php echo $res['vmid']; ?>" data-type="<?php echo $res['type']; ?>">
                                <div class="vm-card-content">
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
                                    <div class="type-label"><?php echo $res['type'] . ' (' . $res['vmid'] . ')'; ?></div>
                                    <?php if ($res['status'] === 'running' && $settings['showVMMetrics']): ?>
                                        <div class="vm-metrics">
                                            <div class="progress-bar">
                                                <span class="metric-icon">🖥️</span>
                                                <div class="progress cpu-progress" style="width: <?php echo $res['cpu_usage']; ?>%;"></div>
                                            </div>
                                            <div class="progress-bar">
                                                <span class="metric-icon">🧪</span>
                                                <div class="progress ram-progress" style="width: <?php echo ($res['memory_total'] > 0 ? ($res['memory_used'] / $res['memory_total'] * 100) : 0); ?>%;"></div>
                                            </div>
                                            <div class="progress-bar">
                                                <span class="metric-icon">💾</span>
                                                <div class="progress disk-progress" style="width: <?php echo ($res['disk_total'] > 0 ? ($res['disk_used'] / $res['disk_total'] * 100) : 0); ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="vm-card-actionbar">
                                    <a href="<?php echo htmlspecialchars($res['web_url']); ?>" target="_blank" class="action-btn" title="Web">🌐</a>
                                    <a href="<?php echo htmlspecialchars($res['ssh_url']); ?>" target="_blank" class="action-btn ssh-btn" title="SSH">🖥️</a>
                                    <?php if ($res['status'] === 'running'): ?>
                                        <button class="action-btn restart-btn" title="Restart">🔄</button>
                                        <button class="action-btn shutdown-btn" title="Shutdown">⏻</button>
                                    <?php else: ?>
                                        <button class="action-btn start-btn" title="Start">▶</button>
                                    <?php endif; ?>
                                    <button class="action-btn stop-btn" title="Force Stop">■</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <!-- Modal for Stop/Shutdown/Restart Confirmation -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <p>Are you sure you want to <span id="modalAction"></span> this container?</p>
            <button class="modal-confirm" id="modalConfirm">Confirm</button>
            <button class="modal-cancel" id="modalCancel">Cancel</button>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-content">
            <h3>Settings</h3>
            <label>
                <input type="checkbox" id="showNodeMetrics" <?php echo $settings['showNodeMetrics'] ? 'checked' : ''; ?>>
                Show Node Metrics in Header
            </label>
            <label>
                <input type="checkbox" id="showVMMetrics" <?php echo $settings['showVMMetrics'] ? 'checked' : ''; ?>>
                Show Metrics on VM Cards
            </label>
            <label>
                Sort Order:
                <select id="sortOrder">
                    <option value="asc" <?php echo $settings['sortOrder'] === 'asc' ? 'selected' : ''; ?>>VMID Ascending</option>
                    <option value="desc" <?php echo $settings['sortOrder'] === 'desc' ? 'selected' : ''; ?>>VMID Descending</option>
                </select>
            </label>
            <label>
                Theme:
                <select id="theme">
                    <option value="dark" <?php echo $settings['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                    <option value="light" <?php echo $settings['theme'] === 'dark' ? 'selected' : ''; ?>>Light</option>
                </select>
            </label>
            <button class="modal-save" id="settingsSave">Save</button>
            <button class="modal-cancel" id="settingsCancel">Cancel</button>
        </div>
    </div>

    <script src="/proxboard/scripts.js"></script>
</body>
</html>