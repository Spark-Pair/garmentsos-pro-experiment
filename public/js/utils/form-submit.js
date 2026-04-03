function initGlobalFormValidation() {
    document.addEventListener('submit', function (e) {
        if (e.target.matches('form')) {
            if (e.target.action.includes('logout')) {
                return;
            }
            if (e.target.hasAttribute('data-skip-validation')) {
                return;
            }
            if (e.target.hasAttribute('data-layout-toggle')) {
                return;
            }

            if (!validateAllInputs()) {
                e.preventDefault();
                if (typeof showMessageBox === 'function') {
                    showMessageBox('error', 'Some fields are incorrect. Please fix them.');
                }
            }
        }

        hideLoader();

        document.querySelectorAll('input[type="amount"]').forEach(input => {
            formatAmountInput(input);
        });
    });
}
