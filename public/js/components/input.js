(() => {
    window.formatUsername = function formatUsername(input) {
        input.value = input.value.toLowerCase().replace(/[^a-z0-9]/g, '');
    };

    window.validateUsername = function validateUsername() {
        const username = document.getElementById('username')?.value || '';
        if (username.length < 6) {
            if (typeof showToast === 'function') {
                showToast('error', 'Username must be at least 6 characters long.');
            } else if (typeof showMessageBox === 'function') {
                showMessageBox('error', 'Username must be at least 6 characters long.');
            }
            return false;
        }
        return true;
    };
})();
