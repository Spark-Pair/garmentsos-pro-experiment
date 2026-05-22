(() => {
    function initRatesIndex() {
        const config = window.__ratesIndex || {};
        const fetchedData = config.setups || [];

        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-3 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="capitalize">${data.type.replace(/_/g, " ")}</span>
                <span class="capitalize">${data.title.replace(/_/g, " ")}</span>
                <span class="uppercase">${data.short_title}</span>
            </div>`;
        };

        window.allDataArray = fetchedData.map((item) => {
            return {
                id: item.id,
                type: item.type,
                title: item.title,
                short_title: item.short_title,
                visible: true,
            };
        });
    }

    window.initRatesIndex = initRatesIndex;

    function boot() {
        if (window.__ratesIndex) {
            initRatesIndex();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
