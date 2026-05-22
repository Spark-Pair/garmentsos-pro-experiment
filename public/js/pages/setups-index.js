(() => {
    function initSetupsIndex() {
        const config = window.__setupsIndex || {};
        window.authLayout = config.authLayout || "table";

        window.createRow = function createRow(data) {
            const shortTitle = data.short_title
                ? `<div class="flex items-center justify-center gap-2">
                        <span class="uppercase font-semibold">${data.short_title}</span>
                        <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full border border-[var(--border-warning)] text-[var(--text-warning)] bg-[var(--bg-warning)]">
                            Global Key
                        </span>
                   </div>`
                : `<span>-</span>`;

            return `
            <div id="${data.id}"
                class="item row relative group grid grid-cols-3 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="capitalize">${data.type.replace(/_/g, " ")}</span>
                <span class="capitalize">${data.title.replace(/_/g, " ")}</span>
                ${shortTitle}
            </div>`;
        };
    }

    window.initSetupsIndex = initSetupsIndex;

    function boot() {
        if (window.__setupsIndex) {
            initSetupsIndex();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
