function initCustomersCreate(config) {
    window.usernames = config?.usernames || [];

    window.validateForNextStep = function() {
        return true;
    }
}

function bootCustomersCreate() {
    if (window.__customersCreate) {
        initCustomersCreate(window.__customersCreate);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootCustomersCreate);
} else {
    bootCustomersCreate();
}
