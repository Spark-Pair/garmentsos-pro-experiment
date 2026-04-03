(() => {
    function autoSelectOptions() {
        document.querySelectorAll('li[data-auto-select="true"]').forEach(li => {
            selectThisOption(li);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoSelectOptions);
    } else {
        autoSelectOptions();
    }
})();
