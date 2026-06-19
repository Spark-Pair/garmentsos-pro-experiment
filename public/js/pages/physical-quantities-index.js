(() => {
    function initPhysicalQuantitiesIndex() {
        const config = window.__physicalQuantitiesIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-[8%_7%_4%_8%_8%_8%_8%_7%_7%_8%_4%_4%_4%_8%_7%] border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out text-xs"
                data-json='${jsonAttr(data)}'>

                <span>${data.article_no}</span>
                <span class="capitalize">${data.processed_by}</span>
                <span>${data.unit}</span>
                <span>${data.total_quantity}</span>
                <span>${data.received_quantity} - Pkts.</span>
                <span>${data.ordered_quantity} - Pkts.</span>
                <span>${data.invoiced_quantity} - Pkts.</span>
                <span>${data.return_quantity} - Pkts.</span>
                <span>${data.adjustment_quantity} - Pkts.</span>
                <span>${data.current_stock} - Pkts.</span>
                <span>${data.a_category}</span>
                <span>${data.b_category}</span>
                <span>${data.c_category}</span>
                <span>${data.remaining_quantity} - Pkts.</span>
                <span>${data.shipment}</span>
            </div>`;
        };
    }

    window.initPhysicalQuantitiesIndex = initPhysicalQuantitiesIndex;

    function boot() {
        if (window.__physicalQuantitiesIndex) {
            initPhysicalQuantitiesIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
