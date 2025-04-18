document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('confirmModal');
    const modalAction = document.getElementById('modalAction');
    const modalConfirm = document.getElementById('modalConfirm');
    const modalCancel = document.getElementById('modalCancel');

    // Show confirmation modal
    function showConfirmModal(action, callback) {
        modalAction.textContent = action;
        modal.style.display = 'flex';
        modalConfirm.onclick = () => {
            callback();
            modal.style.display = 'none';
        };
        modalCancel.onclick = () => {
            modal.style.display = 'none';
        };
    }

    // Replace Start/Restart button with animation
    function replaceButton(card, newStatus, statusData) {
        const currentBtn = card.querySelector('.start-btn, .restart-btn');
        if (!currentBtn) return;

        currentBtn.classList.remove('fade-in');
        currentBtn.classList.add('fade-out');
        setTimeout(() => {
            const isRunning = newStatus === 'running';
            const newBtn = document.createElement('button');
            newBtn.className = `action-btn ${isRunning ? 'restart-btn' : 'start-btn'}`;
            newBtn.title = isRunning ? 'Restart' : 'Start';
            newBtn.textContent = isRunning ? '🔄' : '▶';
            currentBtn.replaceWith(newBtn);
            newBtn.classList.add('fade-in', 'bounce');
            setTimeout(() => newBtn.classList.remove('fade-in', 'bounce'), 300);

            // Update shutdown button visibility
            const shutdownBtn = card.querySelector('.shutdown-btn');
            if (shutdownBtn) {
                if (isRunning) {
                    shutdownBtn.style.display = 'flex';
                } else {
                    shutdownBtn.remove();
                }
            } else if (isRunning) {
                const stopBtn = card.querySelector('.stop-btn');
                const newShutdownBtn = document.createElement('button');
                newShutdownBtn.className = 'action-btn shutdown-btn';
                newShutdownBtn.title = 'Shutdown';
                newShutdownBtn.textContent = '⏻';
                stopBtn.parentNode.insertBefore(newShutdownBtn, stopBtn);
                newShutdownBtn.addEventListener('click', () => handleAction(card, newShutdownBtn, 'shutdown'));
            }

            // Update progress bars
            const metricsDiv = card.querySelector('.vm-metrics');
            if (metricsDiv) {
                if (isRunning) {
                    metricsDiv.style.display = 'flex';
                    updateProgressBars(card, statusData);
                } else {
                    metricsDiv.remove();
                }
            } else if (isRunning) {
                const typeLabel = card.querySelector('.type-label');
                const newMetricsDiv = document.createElement('div');
                newMetricsDiv.className = 'vm-metrics';
                newMetricsDiv.innerHTML = `
                    <div class="progress-bar"><span class="metric-icon">🖥️</span><div class="progress cpu-progress" style="width: ${statusData.cpu_usage}%"></div></div>
                    <div class="progress-bar"><span class="metric-icon">🧪</span><div class="progress ram-progress" style="width: ${statusData.memory_total > 0 ? (statusData.memory_used / statusData.memory_total * 100) : 0}%"></div></div>
                    <div class="progress-bar"><span class="metric-icon">💾</span><div class="progress disk-progress" style="width: ${statusData.disk_total > 0 ? (statusData.disk_used / statusData.disk_total * 100) : 0}%"></div></div>
                `;
                typeLabel.parentNode.insertBefore(newMetricsDiv, typeLabel.nextSibling);
                updateProgressBars(card, statusData);
            }

            // Attach event listener to new button
            newBtn.addEventListener('click', () => handleAction(card, newBtn, isRunning ? 'restart' : 'start'));
        }, 300);
    }

    // Update progress bars for a VM card
    function updateProgressBars(card, statusData) {
        const cpuProgress = card.querySelector('.cpu-progress');
        const ramProgress = card.querySelector('.ram-progress');
        const diskProgress = card.querySelector('.disk-progress');
        if (cpuProgress) cpuProgress.style.width = `${statusData.cpu_usage}%`;
        if (ramProgress) ramProgress.style.width = `${statusData.memory_total > 0 ? (statusData.memory_used / statusData.memory_total * 100) : 0}%`;
        if (diskProgress) diskProgress.style.width = `${statusData.disk_total > 0 ? (statusData.disk_used / statusData.disk_total * 100) : 0}%`;
    }

    // Update node status
    function updateNodeStatus(data) {
        if (data.node_status) {
            document.getElementById('cpu-usage').textContent = `${data.node_status.cpu_usage}%`;
            document.getElementById('ram-usage').textContent = `${data.node_status.memory_used} / ${data.node_status.memory_total} GB`;
            document.getElementById('disk-usage').textContent = `${data.node_status.disk_used} / ${data.node_status.disk_total} GB`;
            const cpuProgress = document.querySelector('.node-metrics .cpu-progress');
            const ramProgress = document.querySelector('.node-metrics .ram-progress');
            const diskProgress = document.querySelector('.node-metrics .disk-progress');
            if (cpuProgress) cpuProgress.style.width = `${data.node_status.cpu_usage}%`;
            if (ramProgress) ramProgress.style.width = `${data.node_status.memory_total > 0 ? (data.node_status.memory_used / data.node_status.memory_total * 100) : 0}%`;
            if (diskProgress) diskProgress.style.width = `${data.node_status.disk_total > 0 ? (data.node_status.disk_used / data.node_status.disk_total * 100) : 0}%`;
        }
    }

    // Handle Start/Stop/Shutdown/Restart actions
    function handleAction(card, btn, action) {
        const vmid = card.dataset.vmid;
        const type = card.dataset.type.toLowerCase();
        const isRunning = card.querySelector('.status-indicator').classList.contains('status-running');

        const executeAction = () => {
            fetch('/proxboard/control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `vmid=${vmid}&type=${type}&action=${action}`
            }).then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            }).then(data => {
                if (data.success) {
                    const indicator = card.querySelector('.status-indicator');
                    const newStatus = (action === 'start' || action === 'restart') ? 'running' : 'stopped';
                    indicator.className = `status-indicator status-${newStatus}`;
                    if (action === 'start' || action === 'stop' || action === 'shutdown') {
                        replaceButton(card, newStatus, {});
                    }
                } else {
                    console.error(`${action} failed:`, data.error);
                }
            }).catch(err => {
                console.error(`${action} failed:`, err.message);
            });
        };

        if ((action === 'stop' || action === 'shutdown' || action === 'restart') && isRunning) {
            showConfirmModal(action, executeAction);
        } else {
            executeAction();
        }
    }

    // Pinning logic
    document.querySelectorAll('.pin-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.vm-card');
            const vmid = parseInt(card.dataset.vmid);
            const isPinned = btn.textContent === '★';
            const pinnedGrid = document.getElementById('pinned-grid');
            const unpinnedGrid = document.getElementById('unpinned-grid');

            btn.textContent = isPinned ? '☆' : '★';
            btn.style.color = isPinned ? '#ffffff' : '#ffd700';

            card.classList.add('fade-out', 'slide-in');
            setTimeout(() => {
                fetch('/proxboard/pin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `vmid=${vmid}&pin=${!isPinned}`
                }).then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Response text:', text.substring(0, 100));
                            throw new Error(`Network response was not ok: ${response.status}`);
                        });
                    }
                    return response.json();
                }).then(data => {
                    if (data.success) {
                        card.classList.remove('fade-out');
                        card.classList.add('fade-in');
                        if (isPinned) {
                            // Insert into unpinnedGrid in vmid order
                            const unpinnedCards = Array.from(unpinnedGrid.querySelectorAll('.vm-card'));
                            let inserted = false;
                            for (const otherCard of unpinnedCards) {
                                const otherVmid = parseInt(otherCard.dataset.vmid);
                                if (vmid < otherVmid) {
                                    unpinnedGrid.insertBefore(card, otherCard);
                                    inserted = true;
                                    break;
                                }
                            }
                            if (!inserted) {
                                unpinnedGrid.appendChild(card);
                            }
                            if (!pinnedGrid.querySelector('.vm-card')) {
                                pinnedGrid.innerHTML = '<p>No pinned resources found.</p>';
                            }
                            if (unpinnedGrid.querySelectorAll('.vm-card').length === 0) {
                                unpinnedGrid.innerHTML = '<p>No unpinned resources found.</p>';
                            }
                        } else {
                            // Insert into pinnedGrid in vmid order
                            const pinnedCards = Array.from(pinnedGrid.querySelectorAll('.vm-card'));
                            // Remove placeholder text if present
                            const placeholder = pinnedGrid.querySelector('p');
                            if (placeholder) placeholder.remove();
                            let inserted = false;
                            for (const otherCard of pinnedCards) {
                                const otherVmid = parseInt(otherCard.dataset.vmid);
                                if (vmid < otherVmid) {
                                    pinnedGrid.insertBefore(card, otherCard);
                                    inserted = true;
                                    break;
                                }
                            }
                            if (!inserted) {
                                pinnedGrid.appendChild(card);
                            }
                            if (!unpinnedGrid.querySelector('.vm-card')) {
                                unpinnedGrid.innerHTML = '<p>No unpinned resources found.</p>';
                            }
                            if (pinnedGrid.querySelectorAll('.vm-card').length === 0) {
                                pinnedGrid.innerHTML = '<p>No pinned resources found.</p>';
                            }
                        }
                        setTimeout(() => card.classList.remove('fade-in', 'slide-in'), 300);
                    } else {
                        console.error('Pin update failed:', data.error);
                        window.location.reload();
                    }
                }).catch(err => {
                    console.error('Pin update failed:', err.message);
                    window.location.reload();
                });
            }, 300);
        });
    });

    // Start/Stop/Shutdown/Restart logic
    document.querySelectorAll('.start-btn, .stop-btn, .shutdown-btn, .restart-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.vm-card');
            const action = btn.classList.contains('start-btn') ? 'start' : 
                          btn.classList.contains('stop-btn') ? 'stop' : 
                          btn.classList.contains('shutdown-btn') ? 'shutdown' : 'restart';
            handleAction(card, btn, action);
        });
    });

    // Auto-refresh status
    setInterval(() => {
        fetch('/proxboard/status.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Object.keys(data.statuses).forEach(vmid => {
                        const card = document.querySelector(`.vm-card[data-vmid="${vmid}"]`);
                        if (card) {
                            const indicator = card.querySelector('.status-indicator');
                            const currentStatus = indicator.classList.contains('status-running') ? 'running' : 'stopped';
                            const newStatus = data.statuses[vmid].status;
                            if (currentStatus !== newStatus) {
                                indicator.className = `status-indicator status-${newStatus}`;
                                replaceButton(card, newStatus, data.statuses[vmid]);
                            } else if (newStatus === 'running') {
                                updateProgressBars(card, data.statuses[vmid]);
                            }
                        }
                    });
                    updateNodeStatus(data);
                }
            })
            .catch(err => console.error('Status refresh failed:', err.message));
    }, 30000); // Every 30 seconds
});