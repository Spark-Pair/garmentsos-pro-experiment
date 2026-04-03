(() => {
    function initUsersCreate() {
        const config = window.__usersCreate || {};
        window.usernames = config.usernames || [];

        window.validateForNextStep = function validateForNextStep() {
            return true;
        };
    }

    window.initUsersCreate = initUsersCreate;

    function boot() {
        if (window.__usersCreate) {
            initUsersCreate();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
