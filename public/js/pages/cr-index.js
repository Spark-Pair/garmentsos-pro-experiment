(() => {
    function initCrIndex() {
        const config = window.__crIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${data.date}</span>
                <span>${data.supplier_name}</span>
                <span>${data.c_r_no}</span>
                <span>${formatNumbersWithDigits(data.amount, 1, 1)}</span>
                <span>${data.voucher_no}</span>
            </div>`;
        };
    }

    window.initCrIndex = initCrIndex;

    function boot() {
        if (window.__crIndex) {
            initCrIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
