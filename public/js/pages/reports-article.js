(() => {
    function initReportsArticle() {
        const config = window.__reportsArticle || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}"
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="w-1/6">${data.invoice_date}</span>
                <span class="w-1/6">${data.article_no}</span>
                <span class="w-1/6">${data.invoice_no}</span>
                <span class="grow">${data.customer_name}</span>
                <span class="w-1/6">${data.invoice_pcs}</span>
            </div>`;
        };
    }

    window.initReportsArticle = initReportsArticle;

    function boot() {
        if (window.__reportsArticle) initReportsArticle();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
