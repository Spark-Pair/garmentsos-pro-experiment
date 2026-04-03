(() => {
    function initAppCommon() {
        const config = window.__appConfig || {};

        window.messageBox = document.getElementById('messageBox');
        window.notificationBox = document.getElementById('notificationBox');

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker
                .register('/service-worker.js')
                .then(() => {})
                .catch(err => console.warn('Service Worker registration failed ❌', err));
        }

        window.closeOnClickOutside = undefined;
        window.escToClose = undefined;
        window.enterToSubmit = undefined;

        if (typeof config.menuShortcuts !== 'undefined') {
            let shortcuts = config.menuShortcuts;
            if (typeof shortcuts === 'string') {
                try {
                    shortcuts = JSON.parse(shortcuts);
                } catch (_) {
                    shortcuts = [];
                }
            }
            if (!Array.isArray(shortcuts)) {
                shortcuts = [];
            }
            window.menu_shortcuts = shortcuts;
        }
        if (typeof config.maxShortcutsLimit === 'number') {
            window.maxShortcutsLimit = config.maxShortcutsLimit;
        }

        if (config.authenticated) {
            window.url = window.location.href;
        }

        window.calculations = {};
        if (config.homeUrl) {
            window.__homeUrl = config.homeUrl;
        }
        if (typeof initNavButtons === 'function') initNavButtons();
        if (typeof initHomeShortcut === 'function' && config.homeUrl) initHomeShortcut();
        if (typeof messageBoxAnimation === 'function') messageBoxAnimation();
        if (typeof initGlobalUI === 'function') initGlobalUI();
        if (config.authenticated && typeof initActivityPing === 'function') initActivityPing();

        if (config.pusherEnabled) {
            window.__pusherKey = config.pusherKey;
            window.__pusherCluster = config.pusherCluster;
            window.__authUserId = config.authUserId;
            window.__routeIsLogin = config.routeIsLogin;
            window.__routeIsSubscriptionExpired = config.routeIsSubscriptionExpired;
            window.__routeIsOrdersCreate = config.routeIsOrdersCreate;
            if (typeof initPusherNotifications === 'function') initPusherNotifications();
        }

        window.doHide = false;
        if (typeof initGlobalLoader === 'function') initGlobalLoader();

        const layoutBtn = document.getElementById('changeLayoutBtn');
        if (config.changeLayoutUrl) {
            window.__changeLayoutUrl = config.changeLayoutUrl;
        } else if (layoutBtn?.dataset?.changeLayoutUrl) {
            window.__changeLayoutUrl = layoutBtn.dataset.changeLayoutUrl;
        }

        if (layoutBtn?.dataset?.layout) {
            window.__authLayout = layoutBtn.dataset.layout;
        } else if (typeof window.authLayout !== 'undefined') {
            window.__authLayout = window.authLayout;
        } else {
            window.__authLayout = window.__authLayout || 'grid';
        }

        if (config.readonlySession && typeof initReadOnlyLock === 'function') {
            initReadOnlyLock();
        }

        if (typeof initAmountInputs === 'function') initAmountInputs();
        if (typeof initGlobalFormValidation === 'function') initGlobalFormValidation();
    }

    function hydrateConfigFromBody() {
        const raw = document.body?.dataset?.appConfig;
        if (!raw) return {};
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('Failed to parse data-app-config', e);
            return {};
        }
    }

    window.initAppCommon = initAppCommon;

    document.addEventListener('DOMContentLoaded', () => {
        const bodyConfig = hydrateConfigFromBody();
        window.__appConfig = Object.assign({}, bodyConfig, window.__appConfig || {});
        initAppCommon();
        document.dispatchEvent(new CustomEvent('app:config:ready'));
    });
})();
