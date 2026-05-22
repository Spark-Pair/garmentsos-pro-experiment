(() => {
function initUtilityAccountsIndex() {
    let authLayout = 'table';

    window.createRow = function(data) {
        return `
        <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
            class="item row relative group grid grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
            data-json='${JSON.stringify(data)}'>

            <span class="capitalize">${data.bill_type}</span>
            <span class="capitalize">${data.location}</span>
            <span class="capitalize">${data.account_title}</span>
            <span class="capitalize">${data.account_no}</span>
        </div>`;
    }
}

window.initUtilityAccountsIndex = initUtilityAccountsIndex;

function boot() {
    initUtilityAccountsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
