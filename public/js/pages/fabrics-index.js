(function () {
    window.createRow = function createRow(data) {
        return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group flex border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-center w-[10%]">${data.date}</span>
                <span class="text-center w-[15%] capitalize">${data.supplier_name ?? data.employee_name}</span>
                <span class="text-center w-[10%]">${data.type ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.fabric ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.color ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.unit ?? "-"}</span>
                <span class="text-center w-[10%]">${data.quantity ?? "-"}</span>
                <span class="text-center w-[20%]">${data.tag ?? "-"}</span>
                <span class="text-center w-[10%] capitalize">${data.remarks ?? "-"}</span>
            </div>`;
    };

    function initFabricsIndex(data) {
        if (data?.authLayout) {
            window.authLayout = data.authLayout;
        }
        if (data?.currentUserRole) {
            window.__currentUserRole = data.currentUserRole;
        }
    }

    window.initFabricsIndex = initFabricsIndex;

    function boot() {
        if (window.__fabricsIndex) {
            initFabricsIndex(window.__fabricsIndex);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
