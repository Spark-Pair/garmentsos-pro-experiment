(() => {
    function initHome() {
        const config = window.__home || {};
        if (!config.notification) return;

        setTimeout(() => {
            showNotification(config.notification.title, config.notification.message);
        }, 1000);
    }

    window.initHome = initHome;

    function boot() {
        if (window.__home) {
            initHome();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
