function initPusherNotifications() {
    if (typeof Pusher === 'undefined') return;
    if (!window.__pusherKey || !window.__pusherCluster) return;
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return;

    const pusher = new Pusher(window.__pusherKey, {
        cluster: window.__pusherCluster,
        forceTLS: true
    });

    if (pusher?.connection) {
        pusher.connection.bind('error', function () {
            // Keep this silent; websocket/network issues should not break the app UI.
        });
    }

    const channel = pusher.subscribe('notifications');

    channel.bind('App\\Events\\NewNotificationEvent', function (data) {
        const dataObject = data?.data || {};
        const title = dataObject.title || '';
        const message = dataObject.message || '';
        const type = dataObject.type || '';
        const isAuthSensitive = type === 'user_inactivated' || type === 'password_reset';

        if (!window.__routeIsLogin && !window.__routeIsSubscriptionExpired) {
            if (isAuthSensitive && dataObject.id == window.__authUserId) {
                showNotification(title, message, type);
                setTimeout(() => {
                    const logoutForm = document.getElementById('logoutForm');
                    if (logoutForm) logoutForm.submit();
                }, 5000);
                return;
            }
        }

        if (window.__routeIsOrdersCreate) {
            if (title === 'New Article Added.') {
                const dateInput = document.querySelector('#date');
                if (dateInput?.value && typeof getDataByDate === 'function') {
                    getDataByDate(dateInput);
                    showNotification(title, message, type);
                    return;
                }
            }
        }

        if (!window.__routeIsLogin && !window.__routeIsSubscriptionExpired && (title || message)) {
            showNotification(title, message, type);
        }
    });
}
