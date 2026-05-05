(() => {
    function initPhysicalQuantitiesIndex() {
        const config = window.__physicalQuantitiesIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-[10%_8%_6%_10%_10%_10%_9%_6%_6%_6%_10%_9%] border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span>${data.article_no}</span>
                <span class="capitalize">${data.processed_by}</span>
                <span>${data.unit}</span>
                <span>${data.total_quantity}</span>
                <span>${data.received_quantity} - Pkts.</span>
                <span>${data.ordered_quantity} - Pkts.</span>
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
