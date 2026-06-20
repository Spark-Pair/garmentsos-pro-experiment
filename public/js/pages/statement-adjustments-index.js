(() => {
    function initStatementAdjustmentsIndex() {
        window.createRow = function createRow(data) {
            return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item row relative group grid grid-cols-8 text-center border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${jsonAttr(data)}'>
                    <span>${data.id}</span>
                    <span data-sort-value="${data.date_raw || ''}">${data.date}</span>
                    <span>${data.category}</span>
                    <span class="col-span-2">${data.name}</span>
                    <span class="capitalize">${data.entry_type}</span>
                    <span>${data.direction}</span>
                    <span data-sort-value="${data.amount_raw}">${data.amount}</span>
                </div>`;
        };

        window.createCard = function createCard(data) {
            return `
                <div id="${data.id}" oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' onclick='${htmlAttr(data.onclick || "")}'
                    class="item card bg-[var(--h-bg-color)] border border-[var(--glass-border-color)]/20 rounded-lg p-4 text-left cursor-pointer hover:border-[var(--primary-color)] transition-all fade-in"
                    data-json='${jsonAttr(data)}'>
                    <div class="flex justify-between gap-3">
                        <h3 class="font-semibold text-[var(--text-color)]">${data.name}</h3>
                        <span class="text-sm">${data.amount}</span>
                    </div>
                    <div class="text-xs text-[var(--secondary-text)] mt-2">${data.category} | ${data.entry_type} | ${data.direction}</div>
                    <div class="text-xs text-[var(--secondary-text)] mt-1">${data.date}</div>
                </div>`;
        };

        window.generateContextMenu = function generateContextMenu(e) {
            e.preventDefault();
            const item = e.target.closest('.item');
            const data = JSON.parse(item.dataset.json);

            createContextMenu({
                item,
                data,
                x: e.pageX,
                y: e.pageY,
                actions: [
                    { id: 'edit', text: 'Edit Balance Entry' },
                ],
            });
        };

        window.generateModal = function generateModal(item) {
            const data = JSON.parse(item.dataset.json);

            createModal({
                id: 'modalForm',
                name: data.name,
                details: {
                    Date: data.date,
                    Category: data.category,
                    Entry: data.entry_type,
                    Transaction: data.direction,
                    Amount: data.amount,
                    Remarks: data.remarks,
                },
                bottomActions: [
                    { id: 'edit', text: 'Edit Balance Entry', dataId: data.id },
                ],
            });
        };
    }

    window.initStatementAdjustmentsIndex = initStatementAdjustmentsIndex;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStatementAdjustmentsIndex);
    } else {
        initStatementAdjustmentsIndex();
    }
})();
