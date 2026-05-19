function messageBoxAnimation() {
    setTimeout(function() {
        const messages = document.querySelectorAll('.alert-message');
        messages.forEach((message) => {
            if (message) {
                message.classList.add('fade-out');
                message.addEventListener('animationend', () => {
                    message.style.display = 'none';
                });
            }
        });
    }, 5000);
}

function renderAlert(type = 'info', message = '') {
    const config = {
        info: {
            bg: 'bg-[var(--secondary-bg-color)]',
            text: 'text-[var(--secondary-text)]',
            icon: 'fa-circle-info',
        },
        success: {
            bg: 'bg-[var(--bg-success)]',
            text: 'text-[var(--text-success)]',
            icon: 'fa-circle-check',
        },
        warning: {
            bg: 'bg-[var(--bg-warning)]',
            text: 'text-[var(--text-warning)]',
            icon: 'fa-triangle-exclamation',
        },
        error: {
            bg: 'bg-[var(--bg-error)]',
            text: 'text-[var(--text-error)]',
            icon: 'fa-circle-exclamation',
        },
    };

    const cfg = config[type] || config.info;

    const wrapper = document.createElement('div');
    wrapper.className = `alert-message ${cfg.bg} ${cfg.text} ps-2 pe-5 py-2 rounded-2xl flex items-center gap-2 fade-in leading-none tracking-wide`;

    const icon = document.createElement('i');
    icon.className = `fas ${cfg.icon} text-lg mr-1`;

    const text = document.createElement('p');
    text.textContent = message;

    wrapper.appendChild(icon);
    wrapper.appendChild(text);

    return wrapper;
}

function showMessageBox(type = 'info', message = '') {
    if (typeof messageBox === 'undefined' || !messageBox) return;
    messageBox.innerHTML = '';
    messageBox.appendChild(renderAlert(type, message));
    messageBoxAnimation();
}

function hideNotification(notificationElem) {
    notificationElem.classList.add('fade-out');
    notificationElem.addEventListener('animationend', () => {
        notificationElem.style.display = 'none';
        notificationElem.remove();
    });
}

function openDropDown(e, trigger) {
    e.stopPropagation();
    const relatedDropDownMenu = trigger.nextElementSibling;

    if (relatedDropDownMenu.classList.contains('hidden')) {
        closeAllDropdowns(relatedDropDownMenu);
        relatedDropDownMenu.classList.remove('hidden');
        setTimeout(() => {
            relatedDropDownMenu.classList.add('opacity-100', 'scale-in');
            relatedDropDownMenu.classList.remove('opacity-0', 'scale-out');
            focusFirstDropdownField(relatedDropDownMenu);
        }, 10);
    } else {
        relatedDropDownMenu.classList.remove('opacity-100', 'scale-in');
        relatedDropDownMenu.classList.add('opacity-0', 'scale-out');
        setTimeout(() => {
            relatedDropDownMenu.classList.add('hidden');
        }, 300);
    }
}

function closeAllDropdowns(skipElement = null) {
    const dropdownMenus = document.querySelectorAll('.dropdownMenu');
    dropdownMenus.forEach(menu => {
        if (menu === skipElement) return;
        menu.classList.remove('opacity-100', 'scale-in');
        menu.classList.add('opacity-0', 'scale-out');
        setTimeout(() => {
            menu.classList.add('hidden');
        }, 300);
    });
}

function focusFirstDropdownField(dropdownMenu) {
    if (!dropdownMenu) return;

    const firstField = dropdownMenu.querySelector('[data-filter-path]');
    if (!firstField) return;

    if (firstField.classList.contains('dbInput')) {
        const targetId = firstField.getAttribute('data-for') || firstField.id;
        const visibleInput = dropdownMenu.querySelector(`#${CSS.escape(targetId)}`);

        if (visibleInput) {
            visibleInput.focus();
            if (typeof visibleInput.select === 'function') {
                visibleInput.select();
            }
        }

        return;
    }

    firstField.focus();
    if (typeof firstField.select === 'function') {
        firstField.select();
    }
}

function showLoader() {
    const loader = document.getElementById('page-loader');
    if (!loader) return;
    loader.classList.remove('hidden');
    loader.classList.remove('fade-out');
    loader.classList.add('fade-in');
}

function hideLoader() {
    const loader = document.getElementById('page-loader');
    if (!loader) return;
    loader.classList.add('hidden');
    loader.classList.add('fade-out');
    loader.classList.remove('fade-in');
}

const previewImage = (event) => {
    const file = event.target.files[0];
    const placeholderIcon = document.querySelector('.placeholder_icon');
    const uploadText = document.querySelector('.upload_text');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (placeholderIcon) {
                placeholderIcon.src = e.target.result;
                placeholderIcon.classList.add('rounded-md', 'w-full', 'h-auto');
            }
            if (uploadText) {
                uploadText.textContent = 'Preview';
            }
        };
        reader.readAsDataURL(file);
    }
};

const previewFileName = (event) => {
    const file = event.target.files[0];
    const uploadText = document.querySelector('.upload_text');

    if (file && uploadText) {
        uploadText.textContent = `Selected: ${file.name}`;
    }
};

function initGlobalUI() {
    document.addEventListener('focus', function(event) {
        if (!event.isTrusted) return;
        if (event.target.matches('input[type=\"date\"]')) {
            try { event.target.showPicker(); } catch (_) {}
        } else if (event.target.matches('input[type=\"month\"]')) {
            try { event.target.showPicker(); } catch (_) {}
        }
    }, true);

    document.addEventListener('contextmenu', e => e.preventDefault());
}
