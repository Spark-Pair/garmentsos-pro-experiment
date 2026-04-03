function switchBtnTogggle(switchBtn) {
    if (window.__appConfig?.readonlySession) {
        if (typeof showMessageBox === 'function') {
            showMessageBox('warning', 'Read-only mode is enabled. You cannot update shortcuts.');
        }
        return;
    }

    if (switchBtn.classList.contains('active')) {
        switchBtn.classList.remove('active');
        updateMenuCustomization(switchBtn.dataset.for, 'not-active');
        return;
    } else {
        if (menu_shortcuts.length >= maxShortcutsLimit) {
            if (typeof showMessageBox === 'function') {
                showMessageBox('error', `You have reached the maximum limit of ${maxShortcutsLimit} shortcuts.`);
            }
            return null;
        }

        switchBtn.classList.add('active');
        updateMenuCustomization(switchBtn.dataset.for, 'active');
    }
}

function updateMenuCustomization(moduleName, newState) {
    if (newState == 'active' && !menu_shortcuts.includes(moduleName)) {
        menu_shortcuts.push(moduleName);
    } else {
        menu_shortcuts = menu_shortcuts.filter(item => item !== moduleName);
    }

    if (typeof window.renderMenuShortcuts === 'function') {
        window.renderMenuShortcuts();
    }
    reRenderInfoInModal('.menuModalInfo', `Enabled: ${menu_shortcuts.length}/${maxShortcutsLimit}`);

    $.ajax({
        url: '/update-menu-shortcuts',
        type: 'POST',
        data: {
            menu_shortcuts
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status === 'success') {
                // noop
            }
        },
        error: function(xhr, status, error) {
            console.error('Menu shortcuts not updated', error);
        }
    });
}
