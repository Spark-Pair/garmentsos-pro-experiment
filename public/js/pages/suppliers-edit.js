function initSuppliersEdit(config) {
    if (config?.supplierHasCustomImage) {
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

function bootSuppliersEdit() {
    if (window.__suppliersEdit) {
        initSuppliersEdit(window.__suppliersEdit);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootSuppliersEdit);
} else {
    bootSuppliersEdit();
}
