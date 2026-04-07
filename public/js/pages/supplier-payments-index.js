(() => {
function initSupplierPaymentsIndex() {
    window.totalAmount = 0;
    window.totalPayment = 0;

    window.createRow = function(data) {
        return `
            <div id="${data.id}"
                class="item row relative group flex justify-between border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-center w-1/7">${data.date}</span>
                <span class="text-center grow">${data.name}</span>
                <span class="text-center w-1/7 capitalize">${data.method}</span>
                <span class="text-center w-1/7">${data.amount}</span>
                <span class="text-center w-1/7">${data.reff_no ?? '-'}</span>
                <span class="text-center w-1/7">${data.voucher_no}</span>
            </div>
        `;
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
