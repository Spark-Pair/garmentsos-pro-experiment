(() => {
function initUtilityAccountsAdd() {
    window.trackBillType = function(elem) {}
    window.trackLocation = function(elem) {}
    window.trackAccount = function(elem) {}
}

window.initUtilityAccountsAdd = initUtilityAccountsAdd;

function boot() {
    initUtilityAccountsAdd();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
