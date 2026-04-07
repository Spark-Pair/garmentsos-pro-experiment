(() => {
    function initPhysicalQuantitiesIndex() {
        const config = window.__physicalQuantitiesIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group flex items-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="w-[10%]">${data.article_no}</span>
                <span class="capitalize w-[7%]">${data.processed_by}</span>
                <span class="w-[8%]">${data.unit}</span>
                <span class="w-[18%]">${data.total_quantity}</span>
                <span class="w-[12%]">${data.received_quantity}</span>
                <span class="w-[12%]">${data.current_stock}</span>
                <span class="w-[12%]">${data.a_category}</span>
                <span class="w-[12%]">${data.b_category}</span>
                <span class="w-[12%]">${data.c_category}</span>
                <span class="w-[12%]">${data.remaining_quantity}</span>
                <span class="w-[10%]">${data.shipment}</span>
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
