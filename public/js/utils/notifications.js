function showNotification(title = '', message = '') {
    const notificationBox = window.notificationBox || document.getElementById('notificationBox');
    if (!notificationBox) return;

    const notificationElement = document.createElement('div');
    notificationElement.className =
        'notification-card bg-[var(--glass-border-color)]/5 backdrop-blur-md text-[var(--secondary-text)] px-5 py-4 border border-[var(--glass-border-color)]/20 rounded-2xl shadow-xl flex items-start gap-4 fade-in relative';

    const content = document.createElement('div');
    content.className = 'flex-1';

    if (title) {
        const titleEl = document.createElement('p');
        titleEl.className = 'font-semibold';
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

    notificationElement.appendChild(content);
    notificationElement.appendChild(closeButton);

    notificationBox.prepend(notificationElement);

    setTimeout(() => hideNotification(notificationElement), 5000);
}
