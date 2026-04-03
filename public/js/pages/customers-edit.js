function initCustomersEdit(config) {
    if (config?.customerHasCustomImage) {
        const placeholderIcon = document.querySelector('.placeholder_icon');
        if (placeholderIcon) {
            placeholderIcon.classList.remove('w-16', 'h-16');
            placeholderIcon.classList.add('rounded-md', 'w-full', 'h-auto');
        }
    }

    window.validateForNextStep = function() {
        return true;
    }
}

function bootCustomersEdit() {
    if (window.__customersEdit) {
        initCustomersEdit(window.__customersEdit);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootCustomersEdit);
} else {
    bootCustomersEdit();
}
