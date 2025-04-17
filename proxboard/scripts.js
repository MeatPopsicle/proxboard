document.addEventListener('DOMContentLoaded', () => {
    // Pinning logic
    document.querySelectorAll('.pin-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.vm-card');
            const vmid = card.dataset.vmid;
            const isPinned = btn.textContent === '★';
            const pinnedGrid = document.getElementById('pinned-grid');
            const unpinnedGrid = document.getElementById('unpinned-grid');

            // Update button state
            btn.textContent = isPinned ? '☆' : '★';
            btn.style.color = isPinned ? '#ffffff' : '#ffd700';

            // Apply fade-out animation
            card.classList.add('fade-out');
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
                        // Move card with fade-in
                        card.classList.remove('fade-out');
                        card.classList.add('fade-in');
                        if (isPinned) {
                            unpinnedGrid.appendChild(card);
                            if (!pinnedGrid.querySelector('.vm-card')) {
                                pinnedGrid.innerHTML = '<p>No pinned resources found.</p>';
                            }
                            if (unpinnedGrid.querySelectorAll('.vm-card').length === 0) {
                                unpinnedGrid.innerHTML = '<p>No unpinned resources found.</p>';
                            }
                        } else {
                            pinnedGrid.appendChild(card);
                            if (!unpinnedGrid.querySelector('.vm-card')) {
                                unpinnedGrid.innerHTML = '<p>No unpinned resources found.</p>';
                            }
                            if (pinnedGrid.querySelectorAll('.vm-card').length === 0) {
                                pinnedGrid.innerHTML = '<p>No pinned resources found.</p>';
                            }
                        }
                        setTimeout(() => card.classList.remove('fade-in'), 300);
                    } else {
                        console.error('Pin update failed:', data.error);
                        window.location.reload();
                    }
                }).catch(err => {
                    console.error('Pin update failed:', err.message);
                    window.location.reload();
                });
            }, 300); // Match fade-out duration
        });
    });

    // Start/Stop logic
    document.querySelectorAll('.start-btn, .stop-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.vm-card');
            const vmid = card.dataset.vmid;
            const type = card.dataset.type.toLowerCase();
            const action = btn.classList.contains('start-btn') ? 'start' : 'stop';

            fetch('/proxboard/control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `vmid=${vmid}&type=${type}&action=${action}`
            }).then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            }).then(data => {
                if (data.success) {
                    // Update status indicator
                    const indicator = card.querySelector('.status-indicator');
                    indicator.className = 'status-indicator ' + (action === 'start' ? 'status-running' : 'status-stopped');
                } else {
                    console.error(`${action} failed:`, data.error);
                }
            }).catch(err => {
                console.error(`${action} failed:`, err.message);
            });
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
                            indicator.className = 'status-indicator ' + (data.statuses[vmid] === 'running' ? 'status-running' : 'status-stopped');
                        }
                    });
                }
            })
            .catch(err => console.error('Status refresh failed:', err.message));
    }, 30000); // Every 30 seconds
});