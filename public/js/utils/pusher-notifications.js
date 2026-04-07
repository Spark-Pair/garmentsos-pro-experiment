function initPusherNotifications() {
    if (typeof Pusher === 'undefined') return;

    const pusher = new Pusher(window.__pusherKey, {
        cluster: window.__pusherCluster,
        forceTLS: true
    });

    const channel = pusher.subscribe('notifications');

    channel.bind('App\\Events\\NewNotificationEvent', function (data) {
        const dataObject = data.data;

        if (!window.__routeIsLogin && !window.__routeIsSubscriptionExpired) {
            if ((dataObject.type === 'user_inactivated' || dataObject.type === 'password_reset')
                && dataObject.id == window.__authUserId) {
                showNotification(dataObject.title, dataObject.message);
                setTimeout(() => {
                    const logoutForm = document.getElementById('logoutForm');
                    if (logoutForm) logoutForm.submit();
                }, 5000);
            }
        }

        if (window.__routeIsOrdersCreate) {
            if (dataObject.title === 'New Article Added.') {
                const dateInput = document.querySelector('#date');
                if (dateInput?.value && typeof getDataByDate === 'function') {
                    getDataByDate(dateInput);
                    showNotification(dataObject.title, dataObject.message);
                }
            }
        }
    });
}
