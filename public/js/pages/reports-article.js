(() => {
    function initReportsArticle() {
        const config = window.__reportsArticle || {};
        window.authLayout = config.authLayout || "table";

        function formatArticleQuantity(pcs, pcsPerPacket, packets = null) {
            const totalPcs = parseFormattedNumber(pcs);
            const unit = parseFormattedNumber(pcsPerPacket);

            if (!unit) {
                if (packets !== null) {
                    return `${formatNumbersWithDigits(parseFormattedNumber(packets), 2, 0)}Pk | ${formatNumbersDigitLess(totalPcs)}Pc`;
                }

                return `${formatNumbersDigitLess(totalPcs)}Pc`;
            }

            const totalPackets = packets === null
                ? totalPcs / unit
                : parseFormattedNumber(packets);

            return `${formatNumbersDigitLess(unit)}U | ${formatNumbersWithDigits(totalPackets, 2, 0)}Pk | ${formatNumbersDigitLess(totalPcs)}Pc`;
        }

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}"
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out text-xs"
                data-json='${jsonAttr(data)}'>

                <span class="w-[9%]">${data.article_no}</span>
                <span class="grow text-left">${data.customer_name}</span>
                <span class="w-[11%]">${data.order_date}</span>
                <span class="w-[10%]">${data.order_no}</span>
                <span class="w-[13%]" data-sort-value="${parseFormattedNumber(data.order_quantity)}">${formatArticleQuantity(data.order_quantity, data.pcs_per_packet)}</span>
                <span class="w-[11%]">${data.invoice_date}</span>
                <span class="w-[10%]">${data.invoice_no}</span>
                <span class="w-[13%]" data-sort-value="${parseFormattedNumber(data.invoice_quantity)}">${formatArticleQuantity(data.invoice_quantity, data.pcs_per_packet)}</span>
            </div>`;
        };

        window.renderCalculation = function renderCalculation(data) {
            const totalOrderQuantityDom = document.querySelector('#calc-bottom > .total-order-quantity .text-right');
            const totalInvoiceQuantityDom = document.querySelector('#calc-bottom > .total-invoice-quantity .text-right');

            if (totalOrderQuantityDom) {
                totalOrderQuantityDom.innerText = formatArticleQuantity(
                    data.total_order_quantity ?? 0,
                    data.total_unit ?? 0,
                    data.total_order_packets ?? null
                );
            }

            if (totalInvoiceQuantityDom) {
                totalInvoiceQuantityDom.innerText = formatArticleQuantity(
                    data.total_invoice_quantity ?? 0,
                    data.total_unit ?? 0,
                    data.total_invoice_packets ?? null
                );
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
