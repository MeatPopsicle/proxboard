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

        // Send update to server
        fetch('/grok/pin.php', {
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
                // Move card to the appropriate section
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
            } else {
                console.error('Pin update failed:', data.error);
                window.location.reload();
            }
        }).catch(err => {
            console.error('Pin update failed:', err.message);
            window.location.reload();
        });
    });
});