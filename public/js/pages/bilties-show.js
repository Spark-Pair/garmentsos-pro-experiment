(() => {
    function initBiltiesShow() {
        const config = window.__biltiesShow || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-6 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${data.date}</span>
                <span class="col-span-2">${data.customer_name}</span>
                <span>${data.invoice_no}</span>
                <span>${data.cargo_name}</span>
                <span>${data.bilty_no}</span>
            </div>`;
        };
    }

    window.initBiltiesShow = initBiltiesShow;

    function boot() {
        if (window.__biltiesShow) {
            initBiltiesShow();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
