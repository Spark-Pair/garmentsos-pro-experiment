(function () {
    window.createRow = function createRow(data) {
        return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-7 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span>${data.date}</span>
                <span>${data.customer}</span>
                <span>${data.article_no}</span>
                <span>${data.invoice_no}</span>
                <span>${data.type === 'adjustment' ? 'Adjustment' : 'Return'}</span>
                <span>${data.quantity + ' - PCs'}</span>
                <span>${formatMoney(data.amount)}</span>
            </div>`;
    };

    function initSalesReturnIndex(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }
    }

    window.initSalesReturnIndex = initSalesReturnIndex;

    function boot() {
        if (window.__salesReturnIndex) {
            initSalesReturnIndex(window.__salesReturnIndex);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
