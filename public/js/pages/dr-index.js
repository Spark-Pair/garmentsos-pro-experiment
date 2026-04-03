(() => {
    function initDrIndex() {
        const config = window.__drIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-3 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${data.customer_name}</span>
                <span>${data.date}</span>
                <span>${data.d_r_no}</span>
            </div>`;
        };
    }

    window.initDrIndex = initDrIndex;

    function boot() {
        if (window.__drIndex) {
            initDrIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
