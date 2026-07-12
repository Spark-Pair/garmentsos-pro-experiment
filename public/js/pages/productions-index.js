(() => {
    function initProductionsIndex() {
        const config = window.__productionsIndex || {};
        window.authLayout = config.authLayout || "table";
        let activeProductionTicketContext = null;

        window.createRow = function createRow(data) {
            data.onclick = "generateProductionTicketModal(this)";
            data.oncontextmenu = "generateProductionContextMenu(event)";
            return `
            <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                class="item row relative group grid grid-cols-6 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${jsonAttr(data)}'>

                <span>${data.article_no}</span>
                <span class="col-span-2">${data.worker_name}</span>
                <span>${data.ticket}</span>
                <span>${data.issue_date}</span>
                <span>${data.receive_date}</span>
            </div>`;
        };

        window.generateProductionTicketModal = function generateProductionTicketModal(item) {
            const data = JSON.parse(item.dataset.json);
            activeProductionTicketContext = data;
            window.previewProductionTicket(data, false);
        };

        window.generateProductionContextMenu = function generateProductionContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest(".item");
            if (!item) return;
            const data = JSON.parse(item.dataset.json);

            createContextMenu({
                item,
                data,
                x: e.pageX,
                y: e.pageY,
                actions: [
                    {
                        id: "print-production-ticket",
                        text: "Preview / Print Ticket",
                        onclick: "printProductionTicketFromContext(this)",
                    },
                ],
            });
        };

        window.printProductionTicketFromContext = function printProductionTicketFromContext(elem) {
            if (activeProductionTicketContext) {
                window.printProductionTicket(activeProductionTicketContext);
            }
        };
    }

    window.initProductionsIndex = initProductionsIndex;

    function boot() {
        if (window.__productionsIndex) {
            initProductionsIndex();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
