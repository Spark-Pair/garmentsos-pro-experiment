(() => {
    function initReportsArticle() {
        const config = window.__reportsArticle || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}"
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span class="w-[13%]">${data.order_date}</span>
                <span class="w-[13%]">${data.invoice_date}</span>
                <span class="w-[12%]">${data.article_no}</span>
                <span class="w-[12%]">${data.order_no}</span>
                <span class="w-[12%]">${data.invoice_no}</span>
                <span class="grow">${data.customer_name}</span>
                <span class="w-[10%]">${data.quantity}</span>
            </div>`;
        };

        window.renderCalculation = function renderCalculation(data) {
            const totalQuantityDom = document.querySelector('#calc-bottom > .total-quantity .text-right');
            if (totalQuantityDom) {
                totalQuantityDom.innerText = formatNumbersDigitLess(data.total_quantity ?? 0);
            }
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
