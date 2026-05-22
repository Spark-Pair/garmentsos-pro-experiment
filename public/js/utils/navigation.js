function initNavButtons() {
    const backBtn = document.getElementById('go_back_button');
    const refreshBtn = document.getElementById('refresh_button');

    if (backBtn) {
        if (window.history.length > 1) {
            backBtn.classList.remove('hidden');
            backBtn.addEventListener('click', () => {
                window.history.back();
            });
        } else {
            backBtn.classList.add('hidden');
        }
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            location.reload();
        });
    }
}

function initHomeShortcut() {
    document.addEventListener('keydown', (e) => {
        if (e.shiftKey && !e.ctrlKey && !e.altKey && (e.code === 'Space' || e.key === ' ')) {
            e.preventDefault();
            if (window.__homeUrl) {
                window.location.href = window.__homeUrl;
            }
        }
    });
}
