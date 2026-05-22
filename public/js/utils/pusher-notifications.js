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
        const targetRoles = Array.isArray(dataObject.target_roles) ? dataObject.target_roles : [];
        const targetUserIds = Array.isArray(dataObject.target_user_ids) ? dataObject.target_user_ids : [];
        const isAuthSensitive = type === 'user_inactivated' || type === 'password_reset';

        if (targetRoles.length > 0 && !targetRoles.includes(window.__authUserRole)) {
            return;
        }

        if (targetUserIds.length > 0 && !targetUserIds.includes(window.__authUserId)) {
            return;
        }

        if (!window.__routeIsLogin && !window.__routeIsSubscriptionExpired) {
            if (isAuthSensitive && dataObject.id == window.__authUserId) {
                showNotification(title, message, type, dataObject);
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
                    showNotification(title, message, type, dataObject);
                    return;
                }
            }
        }

        if (!window.__routeIsLogin && !window.__routeIsSubscriptionExpired && (title || message)) {
            showNotification(title, message, type, dataObject);
        }
    });
}

function initNotificationPolling() {
    if (!window.__notificationsUrl) return;
    if (window.__pusherKey && window.__pusherCluster) return;
    if (window.__routeIsLogin || window.__routeIsSubscriptionExpired) return;
    if (window.__notificationPollingStarted) return;

    window.__notificationPollingStarted = true;

    const poll = () => {
        fetch(window.__notificationsUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(response => response.ok ? response.json() : Promise.reject(response))
            .then(payload => {
                const notifications = Array.isArray(payload?.data) ? payload.data : [];
                notifications.forEach(item => {
                    showNotification(item.title || '', item.message || '', item.type || 'info', item);
                });
            })
            .catch(() => {
                // Silent fallback; polling should not disturb UI.
            });
    };

    poll();
    window.setInterval(poll, 5000);
}
