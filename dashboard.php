<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxmox VM & LXC Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" type="image/x-icon" href="favicon.png">
</head>
<body>
    <div class="container">
        <?php
        $data = require_once __DIR__ . '/logic.php';
        if ($data['error']) {
            echo "<p class='error'>" . htmlspecialchars($data['error']) . "</p>";
        } else {
        ?>
            <div class="pinned-section">
                <h2>Pinned Servers</h2>
                <div class="vm-grid" id="pinned-grid">
                    <?php if (empty($data['pinned_resources'])): ?>
                        <p>No pinned resources found.</p>
                    <?php else: ?>
                        <?php foreach ($data['pinned_resources'] as $res): ?>
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
                <div class="vm-grid" id="unpinned-grid">
                    <?php if (empty($data['unpinned_resources'])): ?>
                        <p>No unpinned resources found.</p>
                    <?php else: ?>
                        <?php foreach ($data['unpinned_resources'] as $res): ?>
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

    <script src="scripts.js"></script>
</body>
</html>