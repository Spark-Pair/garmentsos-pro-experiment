(() => {
    function applyActiveNavState() {
        const url = window.location.href.toLowerCase();
        document.querySelectorAll('.nav-link[data-nav-label]').forEach(el => {
            const label = (el.dataset.navLabel || '').toLowerCase();
            const activators = JSON.parse(el.dataset.activators || '[]').map(tag =>
                String(tag || '').toLowerCase()
            );

            if (label && url.includes(label)) {
                el.classList.add('active');
                return;
            }

            if (activators.some(tag => tag && url.includes(tag))) {
                el.classList.add('active');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyActiveNavState);
    } else {
        applyActiveNavState();
    }
})();
