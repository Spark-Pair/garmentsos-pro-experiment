(() => {
function initSupplierPaymentsIndex() {
    window.totalAmount = 0;
    window.totalPayment = 0;

    window.createRow = function(data) {
        return `
            <div id="${data.id}"
                class="item row relative group flex justify-between border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-center w-1/8">${data.date}</span>
                <span class="text-center w-1/6">${data.name}</span>
                <span class="text-center w-1/8 capitalize">${data.method}</span>
                <span class="text-center w-1/8">${data.amount}</span>
                <span class="text-center w-1/6">${data.source_name}</span>
                <span class="text-center w-1/8">${data.source_type}</span>
                <span class="text-center w-1/8">${data.reff_no ?? '-'}</span>
                <span class="text-center w-1/8">${data.voucher_no}</span>
            </div>
        `;
    }

    window.generateContextMenu = function generateContextMenu(e) {
        e.preventDefault();
        let item = e.target.closest(".item");
        if (!item) return;
        let data = JSON.parse(item.dataset.json);

        let contextMenuData = {
            item: item,
            data: data,
            x: e.pageX,
            y: e.pageY,
            actions: [],
        };

        createContextMenu(contextMenuData);
    };

    window.generateModal = function(item) {
        let data = JSON.parse(item.dataset.json);

        let details = {
            'Date': data.date,
            'Amount': data.amount,
            'Method': data.method,
            'Customer/Self Acc.': data.source_name || '-',
            'Source': data.source_type || '-',
            'Reff No.': data.reff_no ?? '-',
            'Voucher No.': data.voucher_no ?? '-',
        };

        if (data.program_no || data.program_date || data.program_customer) {
            details.hr = true;
            details['Program No'] = data.program_no || '-';
            details['Program Date'] = data.program_date || '-';
            details['Program Customer'] = data.program_customer || '-';
            if (data.program_order_no) details['Order No'] = data.program_order_no;
        }

        if (data.cr_no || data.cr_date) {
            details.hr = true;
            details['CR No'] = data.cr_no || '-';
            details['CR Date'] = data.cr_date || '-';
        }

        if (data.dr_no || data.dr_date) {
            details.hr = true;
            details['DR No'] = data.dr_no || '-';
            details['DR Date'] = data.dr_date || '-';
        }

        let modalData = {
            id: 'modalForm',
            class: 'h-auto',
            name: data.name,
            details,
            bottomActions: [],
        };

        createModal(modalData);
    }

    const listContainer = document.querySelector('.search_container');
    if (listContainer) {
        listContainer.addEventListener('click', (e) => {
            const row = e.target.closest('.row');
            if (!row || !row.dataset.json) return;
            window.generateModal(row);
        });

        listContainer.addEventListener('contextmenu', (e) => {
            const row = e.target.closest('.row');
            if (!row || !row.dataset.json) return;
            e.preventDefault();
            window.generateContextMenu(e);
        });
    }
}

window.initSupplierPaymentsIndex = initSupplierPaymentsIndex;

function boot() {
    initSupplierPaymentsIndex();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
} else {
    boot();
}
})();
