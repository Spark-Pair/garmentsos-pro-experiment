window.__recentNotifications = window.__recentNotifications || new Map();

function showNotification(title = '', message = '', type = 'info') {
    const notificationBox = window.notificationBox || document.getElementById('notificationBox');
    if (!notificationBox) return;

    const notificationKey = `${type}::${title}::${message}`;
    const now = Date.now();
    const lastSeenAt = window.__recentNotifications.get(notificationKey);
    if (lastSeenAt && now - lastSeenAt < 4000) {
        return;
    }
    window.__recentNotifications.set(notificationKey, now);

    setTimeout(() => {
        window.__recentNotifications.delete(notificationKey);
    }, 5000);

    const toneMap = {
        success: {
            border: 'border-[var(--border-success)]/40',
            title: 'text-[var(--text-success)]',
            icon: 'fa-circle-check',
            iconColor: 'text-[var(--text-success)]',
        },
        warning: {
            border: 'border-[var(--border-warning)]/40',
            title: 'text-[var(--text-warning)]',
            icon: 'fa-triangle-exclamation',
            iconColor: 'text-[var(--text-warning)]',
        },
        error: {
            border: 'border-[var(--border-error)]/40',
            title: 'text-[var(--text-error)]',
            icon: 'fa-circle-xmark',
            iconColor: 'text-[var(--text-error)]',
        },
        user_inactivated: {
            border: 'border-[var(--border-error)]/40',
            title: 'text-[var(--text-error)]',
            icon: 'fa-user-lock',
            iconColor: 'text-[var(--text-error)]',
        },
        password_reset: {
            border: 'border-[var(--border-warning)]/40',
            title: 'text-[var(--text-warning)]',
            icon: 'fa-key',
            iconColor: 'text-[var(--text-warning)]',
        },
        info: {
            border: 'border-[var(--glass-border-color)]/20',
            title: 'text-[var(--text-color)]',
            icon: 'fa-circle-info',
            iconColor: 'text-[var(--text-color)]',
        },
    };

    const tone = toneMap[type] || toneMap.info;

    const notificationElement = document.createElement('div');
    notificationElement.className =
        `notification-card bg-[var(--glass-border-color)]/5 backdrop-blur-md text-[var(--secondary-text)] px-5 py-4 border ${tone.border} rounded-2xl shadow-xl flex items-start gap-4 fade-in relative`;

    const iconWrap = document.createElement('div');
    iconWrap.className = 'pt-0.5';
    const icon = document.createElement('i');
    icon.className = `fas ${tone.icon} ${tone.iconColor}`;
    iconWrap.appendChild(icon);

    const content = document.createElement('div');
    content.className = 'flex-1';

    if (title) {
        const titleEl = document.createElement('p');
        titleEl.className = `font-semibold ${tone.title}`;
        titleEl.textContent = title;
        content.appendChild(titleEl);
    }

    const messageEl = document.createElement('p');
    messageEl.className = 'text-sm';
    messageEl.textContent = message;
    content.appendChild(messageEl);

    const closeButton = document.createElement('button');
    closeButton.className =
        'absolute top-2.5 right-3.5 text-md opacity-60 hover:opacity-100 transition-all duration-300 ease-in-out cursor-pointer';
    closeButton.addEventListener('click', () => notificationElement.remove());
    const closeIcon = document.createElement('i');
    closeIcon.className = 'fas fa-xmark';
    closeButton.appendChild(closeIcon);

    notificationElement.appendChild(iconWrap);
    notificationElement.appendChild(content);
    notificationElement.appendChild(closeButton);

    notificationBox.prepend(notificationElement);

    setTimeout(() => hideNotification(notificationElement), 5000);
}
