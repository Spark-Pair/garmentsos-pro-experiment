function initReadOnlyLock() {
    document.querySelectorAll('form').forEach(form => {
        let method = (form.getAttribute('method') || 'GET').toUpperCase();
        const spoof = form.querySelector('input[name="_method"]');
        if (spoof && spoof.value) {
            method = spoof.value.toUpperCase();
        }
        const action = (form.getAttribute('action') || '').toLowerCase();
        const allowReadonly = form.hasAttribute('data-readonly-allow');
        if (method !== 'GET' && !allowReadonly && !action.includes('logout') && form.id !== 'logoutForm') {
            form.addEventListener('submit', (e) => e.preventDefault());
            form.querySelectorAll('input, select, textarea, button').forEach(el => {
                if (el.type !== 'hidden') {
                    el.disabled = true;
                }
            });
        }
    });
}
